<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Product;
use App\Models\Address; // Importar el modelo Address
use App\Models\User; // Importar el modelo User
use Illuminate\Support\Facades\DB;


class PedidoController extends Controller
{
    public function ultimo(Request $request)
    {
        $user = $request->user();

        Log::info('PedidoController@ultimo: Usuario autenticado', [
            'user_id' => $user?->id,
            'token_exists' => !empty($request->bearerToken()),
        ]);

        if (!$user) {
            Log::warning('PedidoController@ultimo: Intento de acceso sin autenticación.');
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $pedidoId = Cache::get('pedido_user_' . $user->id);

        Log::info('PedidoController@ultimo: ID de pedido recuperado de caché', [
            'pedido_id' => $pedidoId,
            'user_id' => $user->id
        ]);

        if (!$pedidoId) {
            Log::info('PedidoController@ultimo: No hay pedido ID en caché para el usuario.', ['user_id' => $user->id]);
            return response()->json(['message' => 'No hay pedido reciente en caché para mostrar. Realiza una nueva compra.'], 404);
        }

        $pedido = Pedido::with([
            'items.product',
            'address',
            'address.extraInfo',
            'user' 
        ])->find($pedidoId);
        
        if (!$pedido) {
            Log::info('PedidoController@ultimo: Pedido no encontrado en DB con el ID de caché.', ['pedido_id_from_cache' => $pedidoId, 'user_id' => $user->id]);
            Cache::forget('pedido_user_' . $user->id);
            return response()->json(['message' => 'El pedido reciente no fue encontrado en la base de datos.'], 404);
        }

        // Asegúrate de que packaging_details se decodifique correctamente si está guardado como string JSON
        if (isset($pedido->packaging_details) && is_string($pedido->packaging_details)) {
            $pedido->packaging_details = json_decode($pedido->packaging_details, true);
        }

        // Prepara las URLs de imagen para los ítems del pedido
        foreach ($pedido->items as $item) {
            if ($item->product && $item->product->image) {
                $item->imagen = asset('storage/' . $item->product->image); 
            } else {
                $item->imagen = asset('images/default.jpg'); 
            }
        }

        Log::info('PedidoController@ultimo: Pedido completo cargado exitosamente', [
            'pedido_id' => $pedido->id,
            'status' => $pedido->status, // Estado de pago
            'shipment_status' => $pedido->shipment_status, // <--- NUEVO: Log el estado de envío
            'total' => $pedido->total,
            'envio' => $pedido->envio,
            'packaging_details_exist' => !empty($pedido->packaging_details),
            'items_count' => $pedido->items->count(),
            'address_extra_info_exist' => isset($pedido->address->extraInfo) && !empty($pedido->address->extraInfo),
            'user_name' => $pedido->user->name ?? 'N/A'
        ]);

        return response()->json($pedido);
    }

    /**
     * Obtiene una lista de pedidos (para administradores).
     * Incluye todas las relaciones necesarias para mostrar los detalles completos.
     *
     * @return \Illuminate->Http->JsonResponse
     */
    public function index()
    {
        // Carga relaciones y ordena por fecha de creación descendente
        return Pedido::with(['user', 'address', 'items.product'])
                        ->orderBy('created_at', 'desc')
                        ->get();
    }

    /**
     * Obtiene los ítems de un pedido específico.
     *
     * @param int $id El ID del pedido.
     * @return \Illuminate->Http->JsonResponse
     */
    public function items($id)
    {
        return PedidoItem::with('product')
            ->where('pedido_id', $id)
            ->get();
    }

    /**
     * Obtiene una lista de pedidos aprobados para el usuario autenticado (Mis Pedidos).
     * Incluye todas las relaciones necesarias para mostrar los detalles completos.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function misPedidos(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $pedidos = Pedido::with([
            'user',
            'items.product',
            'address',
            'address.extraInfo'
        ])
            ->where('user_id', $user->id)
            ->where('status', 'approved') // Solo pedidos con estado de pago 'approved'
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($pedidos as $pedido) {
            if (isset($pedido->packaging_details) && is_string($pedido->packaging_details)) {
                $pedido->packaging_details = json_decode($pedido->packaging_details, true);
            }

            foreach ($pedido->items as $item) {
                if ($item->product && $item->product->image) {
                    $item->imagen = asset('storage/' . $item->product->image);
                } else {
                    $item->imagen = asset('images/default.jpg');
                }
            }
        }

        return response()->json($pedidos);
    }

    /**
     * Prepara los productos de un pedido anterior para ser añadidos al carrito nuevamente.
     *
     * @param Request $request
     * @param int $id El ID del pedido a repetir.
     * @return \Illuminate->Http->JsonResponse
     */
    public function repeatPedido(Request $request, $id)
    {
        $user = $request->user();

        $pedido = Pedido::with('items')
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $productos = $pedido->items
            ->whereNotNull('product_id')
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->cantidad,
                ];
            })->values();

        return response()->json($productos);
    }

    /**
     * Obtiene una lista de envíos manuales (productos que no caben en cajas estándar).
     *
     * @return \Illuminate->Http->JsonResponse
     */
    public function excedidos()
    {
        // Obtener todos los envíos manuales agrupados por pedido_uid
        $pedidosRaw = DB::table('envios_manual')
            ->join('users', 'envios_manual.user_id', '=', 'users.id')
            ->join('addresses', 'envios_manual.address_id', '=', 'addresses.id')
            ->leftJoin('address_extras', 'addresses.id', '=', 'address_extras.address_id')
            ->leftJoin('products', 'envios_manual.product_id', '=', 'products.id')
            ->select(
                'envios_manual.pedido_uid',
                'users.name as user_name',
                'users.email as user_email',
                'users.phone as user_phone',
                'addresses.id as address_id',
                'addresses.calle',
                'addresses.codigo_postal',
                'addresses.municipio',
                'addresses.estado',
                'addresses.colonia',
                'address_extras.tipo_lugar',
                'address_extras.nombre_casa',
                'address_extras.barrio',
                'envios_manual.peso',
                'envios_manual.alto',
                'envios_manual.ancho',
                'envios_manual.largo',
                'envios_manual.cantidad',
                'products.id as product_id',
                'products.name as product_name',
                'envios_manual.created_at'
            )
            ->orderBy('envios_manual.created_at', 'desc')
            ->get();

        // Agrupar por pedido_uid para que cada pedido tenga sus productos en un array
        $pedidosAgrupados = $pedidosRaw->groupBy('pedido_uid')->map(function($items, $pedido_uid) {
            $primerItem = $items->first();

            return [
                'pedido_uid' => $pedido_uid,
                'user' => [
                    'name' => $primerItem->user_name,
                    'email' => $primerItem->user_email,
                    'phone' => $primerItem->user_phone,
                ],
                'address' => [
                    'id' => $primerItem->address_id,
                    'calle' => $primerItem->calle,
                    'codigo_postal' => $primerItem->codigo_postal,
                    'municipio' => $primerItem->municipio,
                    'estado' => $primerItem->estado,
                    'colonia' => $primerItem->colonia,
                    'tipo_lugar' => $primerItem->tipo_lugar,
                    'nombre_casa' => $primerItem->nombre_casa,
                    'barrio' => $primerItem->barrio,
                ],
                'productos' => $items->map(function($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'cantidad' => $item->cantidad,
                        'peso' => $item->peso,
                        'alto' => $item->alto,
                        'ancho' => $item->ancho,
                        'largo' => $item->largo,
                    ];
                })->values(),
                'created_at' => $primerItem->created_at,
            ];
        })->values();

        return response()->json($pedidosAgrupados);
    }


    // <--- NUEVO MÉTODO: Actualizar el estado de envío de un pedido ---
    /**
     * Actualiza el estado de envío de un pedido específico.
     * Requiere autenticación y un estado de envío válido.
     *
     * @param \Illuminate->Http->Request $request
     * @param int $id El ID del pedido a actualizar.
     * @return \Illuminate->Http->JsonResponse
     */
    public function updateShipmentStatus(Request $request, $id)
    {
        $request->validate([
            // 'in_process', 'sent', 'delivered', 'cancelled'
            'shipment_status' => 'required|string|in:in_process,sent,delivered,cancelled', 
        ]);

        $pedido = Pedido::find($id);

        if (!$pedido) {
            Log::warning('PedidoController@updateShipmentStatus: Pedido no encontrado', ['pedido_id' => $id, 'user_id' => $request->user()?->id]);
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        $oldStatus = $pedido->shipment_status;
        $newStatus = $request->shipment_status;

        $pedido->shipment_status = $newStatus;
        $pedido->save();

        Log::info('PedidoController@updateShipmentStatus: Estado de envío de pedido actualizado', [
            'pedido_id' => $id,
            'old_shipment_status' => $oldStatus,
            'new_shipment_status' => $newStatus,
            'user_id' => $request->user()?->id
        ]);

        return response()->json(['message' => 'Estado de envío del pedido actualizado correctamente.', 'pedido' => $pedido]);
    }
}
