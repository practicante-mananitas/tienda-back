<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Address;
use App\Models\User;
use App\Models\Product; // <--- Importar el modelo Product para la validación de stock
use App\Http\Controllers\API\ShippingController;

class PaymentsController extends Controller
{
    public function __construct()
    {
        // Constructor vacío si no hay inyecciones de dependencias a nivel de clase
    }

    public function createPreference(Request $request)
    {
        $user = auth()->user();
        $items = $request->items; // Array de ítems del carrito (del frontend)
        $address_id = $request->address_id;

        if (!$user) {
            \Log::error('PaymentsController: Usuario no autenticado para crear el pedido.', ['request' => $request->all()]);
            return response()->json(['error' => 'Usuario no autenticado para crear el pedido.'], 401);
        }

        if (empty($items) || !$address_id) {
            \Log::error('PaymentsController: Faltan productos o la dirección de envío.', ['items_empty' => empty($items), 'address_id_null' => is_null($address_id), 'request' => $request->all()]);
            return response()->json(['error' => 'Faltan productos o la dirección de envío.'], 422);
        }

        $address = Address::find($address_id);
        if (!$address) {
            \Log::error('PaymentsController: Dirección de envío no encontrada.', ['address_id' => $address_id, 'request' => $request->all()]);
            return response()->json(['error' => 'Dirección de envío no encontrada.'], 404);
        }

        // --- NUEVO PASO CRÍTICO: VALIDACIÓN DE STOCK ANTES DE PROCEDER ---
        $productsToVerify = [];
        foreach ($items as $itemData) {
            $productId = $itemData['id']; // El ID del producto que viene del frontend
            $quantity = (int) $itemData['quantity'];
            
            // Consolidar por si un mismo producto viene separado en el request (aunque el frontend ya lo consolida)
            if (!isset($productsToVerify[$productId])) {
                $productsToVerify[$productId] = 0;
            }
            $productsToVerify[$productId] += $quantity;
        }

        $productsWithInsufficientStock = [];
        foreach ($productsToVerify as $productId => $requestedQuantity) {
            $product = Product::find($productId);

            // Si el producto no se encuentra o no tiene stock suficiente
            if (!$product || $product->stock < $requestedQuantity) {
                $productsWithInsufficientStock[] = [
                    'product_id' => $productId,
                    'product_name' => $product ? $product->name : 'Desconocido',
                    'requested_quantity' => $requestedQuantity,
                    'available_stock' => $product ? $product->stock : 0,
                ];
            }
        }

        if (!empty($productsWithInsufficientStock)) {
            \Log::error('PaymentsController: Intento de compra con stock insuficiente.', ['details' => $productsWithInsufficientStock, 'user_id' => $user->id]);
            return response()->json([
                'error' => 'No hay suficiente stock para algunos productos en tu carrito. Por favor, revisa y ajusta las cantidades antes de continuar.',
                'details' => $productsWithInsufficientStock
            ], 400); // 400 Bad Request o 422 Unprocessable Entity
        }
        // --- FIN DE LA VALIDACIÓN DE STOCK ---


        // --- CALCULAR EL TOTAL DE LOS PRODUCTOS ---
        $totalProductos = 0;
        \Log::info('PaymentsController: Items recibidos en createPreference para cálculo de total', ['items_request' => $items]);
        foreach ($items as $itemData) {
            $unitPrice = (float)($itemData['unit_price'] ?? 0);
            $quantity = (int)($itemData['quantity'] ?? 0);
            $totalProductos += $unitPrice * $quantity;
        }
        \Log::info('PaymentsController: Total de productos calculado final', ['totalProductos_final' => $totalProductos]);

        // --- COTIZAR ENVÍO CON ShippingController ---
        // Instancia del Shipping Controller para recalcular cotización
        $shippingController = new ShippingController(new \App\Services\SkydropxService());

        $shippingQuoteRequest = new Request([
            'address_id' => $address_id,
            'items' => collect($items)->map(function($item) {
                return ['product_id' => $item['id'], 'quantity' => $item['quantity']];
            })->toArray()
        ]);

        $shippingQuoteResponse = $shippingController->quote($shippingQuoteRequest);
        $shippingData = json_decode($shippingQuoteResponse->getContent(), true);

        // Si la cotización de envío falla o requiere manual (esto también puede indicar stock por el ShippingController)
        if (isset($shippingData['error']) || (isset($shippingData['manual']) && $shippingData['manual'] === true)) {
            \Log::error('PaymentsController: Error o cotización manual requerida al obtener packaging details para el pago.', ['shipping_response' => $shippingData, 'request' => $request->all()]);
            // Revisa si el error de shipping controller es por stock para dar un mensaje más específico
            if (isset($shippingData['details']) && !empty($shippingData['details'])) {
                return response()->json([
                    'error' => $shippingData['message'] ?? 'Hubo un problema al cotizar el envío debido a un problema de stock. Por favor, ajusta las cantidades en tu carrito.',
                    'details' => $shippingData['details']
                ], 400);
            }
            return response()->json([
                'error' => $shippingData['message'] ?? 'Hubo un problema al confirmar el costo de envío y el empaquetado. Por favor, intenta de nuevo.'
            ], 400);
        }

        $calculated_envio_cost = floatval($shippingData['total_envio'] ?? 0);
        $packagingDetails = $shippingData['cajas_usadas'] ?? null;
        \Log::info('PaymentsController: Costo de envío calculado y packaging details', ['calculated_envio_cost' => $calculated_envio_cost, 'packagingDetails' => $packagingDetails]);

        // --- CALCULAR FECHA MÁXIMA DE ENTREGA ---
        $estimatedDeliveryDays = (int) ($shippingData['dias_entrega'] ?? 0); 
        $additionalDays = 3;

        $fechaMaximaEntrega = Carbon::now()->addDays($estimatedDeliveryDays + $additionalDays);
        \Log::info('PaymentsController: Fecha Máxima de Entrega Calculada', [
            'estimated_delivery_days' => $estimatedDeliveryDays,
            'additional_days' => $additionalDays,
            'fecha_maxima_entrega' => $fechaMaximaEntrega->toDateString()
        ]);

        // --- INICIAR TRANSACCIÓN DE BASE DE DATOS ---
        DB::beginTransaction();

        try {
            // 1. Crear el Pedido en tu base de datos
            $externalReference = uniqid('pedido-');
            $finalTotal = $totalProductos + $calculated_envio_cost;
            \Log::info('PaymentsController: Creando Pedido', [
                'user_id' => $user->id, 'address_id' => $address_id, 'total_productos' => $totalProductos,
                'calculated_envio_cost' => $calculated_envio_cost, 'final_total_pedido' => $finalTotal,
                'external_reference' => $externalReference, 'packaging_details_is_null' => is_null($packagingDetails),
                'fecha_maxima_entrega' => $fechaMaximaEntrega->toDateString()
            ]);

            $pedido = Pedido::create([
                'user_id' => $user->id,
                'address_id' => $address_id,
                'total' => $finalTotal,
                'envio' => $calculated_envio_cost,
                'status' => 'pending',
                'payment_id' => null,
                'external_reference' => $externalReference,
                'packaging_details' => $packagingDetails,
                'fecha_maxima_entrega' => $fechaMaximaEntrega,
            ]);
            \Log::info('PaymentsController: Pedido creado en DB', ['pedido_id' => $pedido->id, 'pedido_data' => $pedido->toArray()]);

            // 2. Crear los ítems del Pedido
            foreach ($items as $itemData) {
                $productId = isset($itemData['id']) ? (int) $itemData['id'] : null;
                if (is_null($productId)) {
                    \Log::warning('PaymentsController: Item sin product_id válido.', ['itemData' => $itemData]);
                }
                PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'product_id' => $productId,
                    'cantidad' => (int)($itemData['quantity'] ?? 0),
                    'precio_unitario' => floatval($itemData['unit_price'] ?? 0),
                ]);
            }
            \Log::info('PaymentsController: PedidoItems creados para pedido', ['pedido_id' => $pedido->id]);

            // Lógica de Mercado Pago existente
            $mpItems = [];
            foreach ($items as $item) {
                $mpItems[] = [
                    'id' => $item['id'] ?? null,
                    'title' => $item['name'] ?? 'Producto sin nombre',
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => floatval($item['unit_price']),
                    'currency_id' => 'MXN',
                    'picture_url' => $item['picture_url'] ?? null
                ];
            }

            if ($calculated_envio_cost > 0) {
                $mpItems[] = [
                    'id' => 'SHIPPING_COST',
                    'title' => 'Costo de envío',
                    'quantity' => 1,
                    'unit_price' => floatval($calculated_envio_cost),
                    'currency_id' => 'MXN',
                    'picture_url' => 'https://example.com/shipping-icon.png'
                ];
            }
            
            $payload = [
                'items' => $mpItems,
                'back_urls' => [
                    'success' => 'https://3dab-2806-104e-1b-54c1-6129-f572-3525-cd2d.ngrok-free.app/#/pago/exito?pedido_id=' . $pedido->id,
                    'failure' => 'https://3dab-2806-104e-1b-54c1-6129-f572-3525-cd2d.ngrok-free.app/pago/error',
                    'pending' => 'https://3dab-2806-104e-1b-54c1-6129-f572-3525-cd2d.ngrok-free.app/pago/pendiente',
                ],
                'auto_return' => 'approved',
                'external_reference' => $pedido->external_reference,
                'notification_url' => 'https://6591-2806-104e-1b-54c1-6129-f572-3525-cd2d.ngrok-free.app/api/webhook/mercadopago',
                'metadata' => [
                    'address_id' => $address_id,
                    'user_id' => $user->id,
                    'pedido_id' => $pedido->id,
                ],
            ];
            \Log::info('PaymentsController: Payload enviado a Mercado Pago', ['payload' => $payload]);

            $response = Http::withToken(env('MP_ACCESS_TOKEN'))
                ->post('https://api.mercadopago.com/checkout/preferences', $payload);

            if ($response->successful()) {
                $preferenceData = $response->json();
                $pedido->payment_id = $preferenceData['id'];
                $pedido->save();
                \Log::info('PaymentsController: ID de preferencia MP guardado en pedido.', ['pedido_id' => $pedido->id, 'mp_preference_id' => $preferenceData['id']]);
            } else {
                \Log::error('PaymentsController: Error: Preferencia de Mercado Pago no devolvió ID o falló la creación.', ['preference_response' => $response->json(), 'payload' => $payload, 'request' => $request->all()]);
                throw new \Exception('La preferencia de Mercado Pago no devolvió un ID válido o falló la creación.');
            }

            DB::commit();
            \Log::info('PaymentsController: Transacción de DB confirmada.');

            \Log::info('MP payload', $payload);
            \Log::info('MP response', $response->json());

            return response()->json($response->json());
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('PaymentsController: Error al crear preferencia de pago o pedido: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'request_data' => $request->all()]);
            return response()->json(['error' => 'Error al procesar el pago. Por favor, intenta de nuevo.'], 500);
        }
    }
}
