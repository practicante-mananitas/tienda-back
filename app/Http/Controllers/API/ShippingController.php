<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Address;
use App\Services\SkydropxService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{

    protected $skydropx;

    public function __construct(SkydropxService $skydropx)
    {
        $this->skydropx = $skydropx;
    }

   public function quote(Request $request)
{
    $user = auth('api')->user(); // 👈 fuerza el guard correcto
    $address = Address::find($request->address_id);
    $token = $this->skydropx->getAccessToken();
    $items = $request->items;

    if (!$address || !$items || count($items) === 0) {
        return response()->json(['error' => 'Faltan datos para cotizar.'], 422);
    }

    $totalParcel = [
        'weight' => 0,
        'height' => 0,
        'width' => 0,
        'length' => 0,
    ];

    foreach ($items as $item) {
        $product = Product::find($item['product_id']);
        if (!$product) continue;

        $totalParcel['weight'] += $product->weight * $item['quantity'];
        $totalParcel['height'] += $product->height * $item['quantity'];
        $totalParcel['width']  += $product->width  * $item['quantity'];
        $totalParcel['length'] += $product->length * $item['quantity'];
    }

    // ✅ Calcular peso volumétrico
    $pesoVolumetrico = ($totalParcel['height'] * $totalParcel['width'] * $totalParcel['length']) / 5000;
    $pesoFacturable = max($totalParcel['weight'], $pesoVolumetrico);

    // ✅ Si es menor a 1, subirlo a 1
    if ($pesoFacturable < 1) {
        $pesoFacturable = 1;
    }

    // ✅ Actualizar el peso a usar para cotización
    $totalParcel['weight'] = $pesoFacturable;


if ($this->requiereEnvioManual($totalParcel)) {
    foreach ($items as $item) {
        $product = Product::find($item['product_id']);
        if (!$product) continue;

        DB::table('envios_manual')->insert([
            'user_id'    => $user->id,
            'address_id' => $address->id,
            'peso'       => $product->weight * $item['quantity'],
            'alto'       => $product->height,
            'ancho'      => $product->width,
            'largo'      => $product->length,
            'product_id' => $product->id,
            'cantidad'   => $item['quantity'],
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    return response()->json([
        'manual' => true,
        'message' => 'Este pedido será cotizado manualmente. Recibirás el costo por correo.'
    ]);
}

    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ])->post('https://api-pro.skydropx.com/api/v1/quotations', [
        "quotation" => [
            "address_from" => [
                "country_code" => "MX",
                "postal_code" => env('SKYDROPX_ORIGIN_CP'),
                "area_level1" => env('SKYDROPX_ORIGIN_STATE'),
                "area_level2" => env('SKYDROPX_ORIGIN_CITY'),
                "area_level3" => env('SKYDROPX_ORIGIN_COLONY'),
                "street1" => env('SKYDROPX_ORIGIN_STREET'),
                "reference" => env('SKYDROPX_ORIGIN_REFERENCE'),
                "name" => env('SKYDROPX_ORIGIN_NAME'),
                "company" => env('SKYDROPX_ORIGIN_NAME'),
                "phone" => env('SKYDROPX_ORIGIN_PHONE'),
                "email" => env('SKYDROPX_ORIGIN_EMAIL')
            ],
            "address_to" => [
                "country_code" => "MX",
                "postal_code" => $address->codigo_postal,
                "area_level1" => $address->estado ?? 'Estado',
                "area_level2" => $address->municipio,
                "area_level3" => $address->colonia,
                "street1" => $address->calle,
                "reference" => $address->indicaciones_entrega ?? "Referencia",
                "name" => "Cliente",
                "company" => "Cliente",
                "phone" => "7777777777",
                "email" => "cliente@email.com"
            ],
            "parcel" => [
                "length" => $totalParcel['length'],
                "width" => $totalParcel['width'],
                "height" => $totalParcel['height'],
                "weight" => $totalParcel['weight']
            ]
        ]
    ]);

    \Log::info('Skydropx status', ['code' => $response->status()]);
    \Log::info('Skydropx body', ['body' => $response->json()]);

    if (!$response->successful()) {
        return response()->json(['error' => 'Error al cotizar con Skydropx.'], 500);
    }

    $data = $response->json();

    // Si la cotización aún no está lista, esperar y volver a consultar
if (!($data['is_completed'] ?? false)) {
    $reintentos = 0;
    do {
        sleep(2); // espera 2 segundos
        $followUpResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->get("https://api-pro.skydropx.com/api/v1/quotations/" . $data['id']);

        \Log::info("Follow-up intento #" . ($reintentos + 1), [
            'status' => $followUpResponse->status(),
            'body' => $followUpResponse->json()
        ]);

        $reintentos++;
        $data = $followUpResponse->json();

        if (!isset($data['is_completed'])) {
            $data['is_completed'] = false;
        }

    } while (!$data['is_completed'] && $reintentos < 3);

    if (!$data['is_completed']) {
        return response()->json(['error' => 'La cotización no pudo completarse tras varios intentos.'], 500);
    }
}


    // Filtrar tarifas válidas
    $rates = collect($data['rates'] ?? [])->filter(function ($r) {
        return in_array($r['status'] ?? '', ['price_found_internal', 'price_found_external']) &&
               isset($r['total']) && is_numeric($r['total']);
    });

    $dhlRate = $rates->firstWhere('provider_name', 'dhl');
    $estafetaRate = $rates->firstWhere('provider_name', 'estafeta');
    $anyRate = $rates->first();

    if ($dhlRate) {
        return response()->json([
            'amount' => $dhlRate['amount'],
            'carrier' => 'DHL',
            'service' => $dhlRate['provider_service_name'] ?? null,
            'days' => $dhlRate['days'] ?? null,
            'total' => $dhlRate['total']
        ]);
    }

    if ($estafetaRate) {
        return response()->json([
            'amount' => $estafetaRate['amount'],
            'carrier' => 'Estafeta',
            'service' => $estafetaRate['provider_service_name'] ?? null,
            'days' => $estafetaRate['days'] ?? null,
            'total' => $estafetaRate['total'],
            'fallback' => true
        ]);
    }

    if ($anyRate) {
        return response()->json([
            'amount' => $anyRate['amount'],
            'carrier' => $anyRate['provider_name'],
            'service' => $anyRate['provider_service_name'] ?? null,
            'days' => $anyRate['days'] ?? null,
            'total' => $anyRate['total'],
            'fallback' => true
        ]);
    }

    return response()->json(['error' => 'No se encontró tarifa válida de ningún proveedor.'], 404);



        return response()->json([
            'amount' => $dhlRate['amount'],
            'response' => $data // opcional para debug, puedes remover en producción
        ]);
    }

private function requiereEnvioManual(array $parcel): bool
{
    $pesoVolumetrico = ($parcel['height'] * $parcel['width'] * $parcel['length']) / 5000;
    $pesoFacturable = max($parcel['weight'], $pesoVolumetrico);

    return $pesoFacturable > 50;
}

}

