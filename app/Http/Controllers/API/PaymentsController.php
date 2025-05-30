<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class PaymentsController extends Controller
{
    public function createPreference(Request $request)
    {
        $items = $request->items;
        $envio = $request->envio ?? 0;

        $mpItems = array_map(function ($item) {
            return [
                'title' => $item['name'] ?? 'Producto sin nombre',
                'quantity' => $item['quantity'],
                'unit_price' => floatval($item['unit_price']),
                'currency_id' => 'MXN',
                // 'address_id' => $item['address_id'] ?? null,
                // 'user_id' => $item['user_id'] ?? null,
            ];
        }, $items);

        // Agrega el envío como producto
        if ($envio > 0) {
            $mpItems = array_map(function ($item) {
                return [
                    'id' => $item['id'] ?? null,
                    'title' => $item['name'] ?? 'Producto sin nombre',
                    'quantity' => $item['quantity'],
                    'unit_price' => floatval($item['unit_price']),
                    'currency_id' => 'MXN',
                    'picture_url' => $item['picture_url'] ?? null
                ];
            }, $items);

            // ✅ Agrega el envío como un producto extra al final del arreglo
            if ($envio > 0) {
                $mpItems[] = [
                    'title' => 'Costo de envío',
                    'quantity' => 1,
                    'unit_price' => floatval($envio),
                    'currency_id' => 'MXN'
                ];
            }
        }
        $payload = [
            'items' => $mpItems,
            'back_urls' => [
                'success' => 'https://0c0e-2806-104e-1b-4128-55a4-6517-1fce-5804.ngrok-free.app/#/pago/exito',
                'failure' => 'https://0c0e-2806-104e-1b-4128-55a4-6517-1fce-5804.ngrok-free.app/pago/error',
                'pending' => 'https://0c0e-2806-104e-1b-4128-55a4-6517-1fce-5804.ngrok-free.app/pago/pendiente',
            ],
            'auto_return' => 'approved',
            'external_reference' => uniqid('pedido-'),
            'notification_url' => 'https://447b-2806-104e-1b-4128-55a4-6517-1fce-5804.ngrok-free.app/api/webhook/mercadopago',
            'metadata' => [
                'address_id' => $request->address_id,
                'user_id' => auth()->id(), // 👌 ya no necesitas confiar en lo que venga del frontend
            ],
        ];



        $response = Http::withToken(env('MP_ACCESS_TOKEN'))
            ->post('https://api.mercadopago.com/checkout/preferences', $payload);

            \Log::info('MP payload', $payload); // LOG
            \Log::info('MP response', $response->json()); // LOG

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['error' => 'No se pudo generar el pago'], 500);
    }
}
