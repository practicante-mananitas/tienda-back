<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Services\SkydropxService;
use Illuminate\Support\Facades\Http;

class ShippingController extends Controller
{
    
    public function cotizarEnvio(Request $request)
    {
        
        $token = env('SKYDROPX_API_KEY'); // asegúrate que esté en tu .env
        \Log::info('Token cargado: ' . $token);

        $cp_origen = '62586';
        $cp_destino = $request->codigo_postal;

        $items = $request->items;

        // Armar parcels desde productos
        $parcels = [];
foreach ($items as $item) {
    $product = Product::find($item['product_id']);

    if (!$product) {
        \Log::error("Producto no encontrado: " . $item['product_id']);
        return response()->json(['error' => 'Producto no encontrado'], 404);
    }

    $parcels[] = [
        'weight' => max($product->weight ?? 1, 1),
        'height' => $product->height ?? 10,
        'width'  => $product->width ?? 10,
        'length' => $product->length ?? 10,
    ];
}


        $response = Http::withHeaders([
            'Authorization' => "Token token={$token}",
            'Content-Type' => 'application/json',
        ])->post('https://app.skydropx.com/api/v1/quotations', [
            'address_from' => ['postal_code' => $cp_origen],
            'address_to'   => ['postal_code' => $cp_destino],
            'parcels'      => $parcels,
        ]);

        if ($response->failed()) {
            \Log::error("Respuesta de Skydropx: " . $response->body());

            return response()->json(['error' => 'Fallo la cotización'], 500);
        }

        // ✅ Solo DHL
        $opciones = collect($response->json('data'))->filter(function ($opcion) {
            return strtolower($opcion['carrier']) === 'dhl';
        })->values();

        return response()->json(['data' => $opciones]);
    }

}

