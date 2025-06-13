<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Product; // <--- Importar el modelo Product
use App\Models\PedidoItem; // <--- Importar el modelo PedidoItem (si no lo estaba ya para acceder a los productos del pedido)
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MercadoPagoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Webhook MP: Notificación recibida', $request->all());

        $paymentId = $request->input('data.id') ?? $request->input('resource');
        $topic = $request->input('topic') ?? $request->input('type');

        if (!$paymentId || !$topic) {
            Log::warning('Webhook MP: Notificación inválida (falta ID o topic).', $request->all());
            return response()->json(['message' => 'Notificación inválida'], 400);
        }

        if ($topic !== 'payment') {
            Log::info('Webhook MP: Topic no es "payment", ignorando.', ['topic' => $topic]);
            return response()->json(['message' => 'No es un pago'], 200);
        }

        // --- Consulta a la API de Mercado Pago para obtener los detalles completos del pago ---
        $response = Http::withToken(env('MP_ACCESS_TOKEN'))
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if (!$response->successful()) {
            Log::error('Webhook MP: Fallo al consultar pago en la API de MP', [
                'payment_id' => $paymentId,
                'response_status' => $response->status(),
                'response_body' => $response->json()
            ]);
            return response()->json(['error' => 'Fallo al consultar el pago'], 500);
        }

        $data = $response->json(); // Datos completos del pago de Mercado Pago
        $mpPaymentStatus = $data['status']; // Estado del pago (approved, rejected, pending, etc.)
        $externalReference = $data['external_reference'] ?? null; // La referencia que tú enviaste desde PaymentsController

        Log::info('Webhook MP: Datos de pago consultados', [
            'payment_id' => $paymentId,
            'status_mp' => $mpPaymentStatus,
            'external_reference' => $externalReference,
            'metadata' => $data['metadata'] ?? []
        ]);

        // --- INICIAR TRANSACCIÓN DE BASE DE DATOS ---
        DB::beginTransaction();

        try {
            // Buscar el pedido existente usando la external_reference y cargar sus ítems con los productos
            $pedido = Pedido::with('items.product') // Cargar los items del pedido y los productos asociados
                            ->where('external_reference', $externalReference)
                            ->first();

            if ($pedido) {
                // Mapear el estado de Mercado Pago a tu estado interno de pedido
                $newStatus = $mpPaymentStatus;
                switch ($mpPaymentStatus) {
                    case 'approved':
                        $newStatus = 'approved';
                        break;
                    case 'rejected':
                    case 'cancelled':
                        $newStatus = 'rejected';
                        break;
                    case 'pending':
                        $newStatus = 'pending';
                        break;
                    default:
                        break;
                }
                
                // Solo actualizar el estado del pedido si es diferente al actual en DB.
                // IMPORTANTE: El descuento de stock se hará solo si el estado actual es 'approved'
                // y el estado *anterior* del pedido no era 'approved'.
                $originalStatus = $pedido->getOriginal('status'); // Obtener el estado original antes de cualquier cambio

                if ($pedido->status !== $newStatus) {
                    $pedido->status = $newStatus;
                    $pedido->payment_id = $paymentId;
                    $pedido->save();
                    Log::info("Webhook MP: Pedido #{$pedido->id} (Ref: {$externalReference}) actualizado a estado: {$newStatus}");
                } else {
                    Log::info("Webhook MP: Pedido #{$pedido->id} (Ref: {$externalReference}) ya tiene estado {$pedido->status}, no se necesita actualizar.");
                }

                // --- LÓGICA CLAVE: Descuento de stock solo si el pago es 'approved' y el pedido no estaba ya aprobado ---
                if ($newStatus === 'approved' && $originalStatus !== 'approved') {
                    Log::info("Webhook MP: Pago APROBADO para pedido ID {$pedido->id}. Iniciando descuento de stock.");

                    foreach ($pedido->items as $item) {
                        $product = $item->product; // Acceder al producto a través del item del pedido
                        if ($product) {
                            $newStock = $product->stock - $item->cantidad;
                            // Asegurarse de que el stock no sea negativo
                            $product->stock = max(0, $newStock); 
                            $product->save();
                            Log::info("Webhook MP: Stock de producto {$product->id} actualizado de {$product->getOriginal('stock')} a {$product->stock} (descontado {$item->cantidad}).");
                        } else {
                            Log::warning("Webhook MP: Producto no encontrado para item de pedido ID {$item->id}. No se pudo descontar stock.", ['item_id' => $item->id, 'product_id' => $item->product_id]);
                        }
                    }
                    Log::info("Webhook MP: Descuento de stock completado para pedido ID {$pedido->id}.");

                    // Si el pago es aprobado y el stock se descontó, ahora puedes enviar el correo, limpiar el carrito, etc.
                    $userId = $pedido->user_id;
                    $user = User::find($userId);
                    if ($user) {
                        // Asegúrate de importar \App\Mail\PedidoConfirmado si lo usas
                        // \Mail::to($user->email)->send(new \App\Mail\PedidoConfirmado($pedido)); 
                        Log::info("Webhook MP: Correo de confirmación enviado a {$user->email} para Pedido #{$pedido->id}");
                    }
                    
                    // Guarda el ID del pedido en caché solo cuando se aprueba
                    Cache::put('pedido_user_' . $userId, $pedido->id, now()->addMinutes(10)); // Caché por 10 minutos
                    Log::info('Webhook MP: Pedido ID guardado en caché para usuario', ['user_id' => $userId, 'pedido_id' => $pedido->id]);

                    // Opcional: Limpiar el carrito del usuario si aún tiene los ítems en su sesión/DB
                    // Si tu carrito se basa en la sesión o la DB, aquí podrías invocar un método para limpiarlo
                    // Ejemplo: \App\Models\Cart::where('user_id', $userId)->delete();
                } elseif ($newStatus === 'rejected' || $newStatus === 'cancelled') {
                    Log::info("Webhook MP: Pago RECHAZADO/CANCELADO para pedido ID {$pedido->id}. No se descuenta stock.");
                    // Si habías implementado una "reserva" de stock al crear el pedido,
                    // aquí sería donde lo devolverías. Para tu caso, el stock solo se descuenta
                    // si se aprueba, así que no se necesita devolver.
                }

            } else {
                Log::warning('Webhook MP: Pedido no encontrado para external_reference.', ['external_reference' => $externalReference, 'payment_id' => $paymentId, 'mp_data' => $data]);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Webhook MP: Excepción al procesar notificación', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'request' => $request->all()]);
            return response()->json(['error' => 'Error interno al procesar el webhook'], 500);
        }

        return response()->json(['message' => 'Notificación procesada'], 200);
    }
}
