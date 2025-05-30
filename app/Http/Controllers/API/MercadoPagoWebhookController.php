<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Address;

class MercadoPagoWebhookController extends Controller
{
   public function handle(Request $request)
{
    // Modo Producción o Prueba
    $paymentId = $request->input('data.id') ?? $request->input('resource');
    $topic = $request->input('topic') ?? $request->input('type');

    if ($topic !== 'payment') {
        return response()->json(['message' => 'No es un pago'], 200);
    }

    $response = Http::withToken(env('MP_ACCESS_TOKEN'))
        ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

    if (!$response->successful()) {
        \Log::error('Fallo al consultar pago', ['id' => $paymentId]);
        return response()->json(['error' => 'Fallo al consultar el pago'], 500);
    }

    $data = $response->json();

    if ($data['status'] === 'approved') {
        \Log::info('Pago aprobado', ['payment' => $data]);

           \Log::info('Metadata recibida', $data['metadata'] ?? []);

        // ✅ Evita duplicados
        if (Pedido::where('payment_id', $paymentId)->exists()) {
            return response()->json(['message' => 'Ya procesado'], 200);
        }

        $reference = $data['external_reference'];
        $addressId = $data['metadata']['address_id'] ?? null;
        $userId = $data['metadata']['user_id'] ?? null;

        // ✅ Intenta obtener el user_id desde la dirección
        // $userId = null;

        $pedido = Pedido::create([
            'external_reference' => $reference,
            'payment_id' => $paymentId,
            'status' => $data['status'],
            'total' => $data['transaction_amount'],
            'address_id' => $addressId,
            'user_id' => $userId,
        ]);

        foreach ($data['additional_info']['items'] ?? [] as $item) {
            \Log::info('🧾 Item recibido del pago:', $item);

            PedidoItem::create([
                'pedido_id' => $pedido->id,
                'product_id' => isset($item['id']) ? (int) $item['id'] : null,
                'producto' => $item['title'] ?? $item['name'] ?? 'Producto sin nombre',
                'cantidad' => $item['quantity'] ?? 1,
                'precio_unitario' => $item['unit_price'] ?? 0,
            ]);
        }
 
        // ✅ Guarda el ID del pedido en caché para usarlo en Angular
            \Cache::put('pedido_user_' . $userId, $pedido->id, now()->addMinutes(10));

            // ✅ Enviar correo de confirmación
            $user = \App\Models\User::find($userId);
            if ($user) {
                \Mail::to($user->email)->send(new \App\Mail\PedidoConfirmado($pedido));
            }
            
        return response()->json(['message' => 'Pedido registrado'], 200);
    }

    return response()->json(['message' => 'Pago no aprobado'], 200);
}
}
