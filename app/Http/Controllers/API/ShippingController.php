<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Address;
use App\Services\SkydropxService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShippingController extends Controller
{
    protected $skydropx;

    const VOLUME_FILL_THRESHOLD = 0.80;
    const MAJOR_DIMENSION_UTILIZATION_THRESHOLD = 0.85;

    public function __construct(SkydropxService $skydropx)
    {
        $this->skydropx = $skydropx;
    }

    public function quote(Request $request)
    {
        $user = auth('api')->user();
        $address = Address::find($request->address_id);
        $token = $this->skydropx->getAccessToken();
        $items = $request->items;

        if (!$address || !$items || count($items) === 0) {
            return response()->json(['error' => 'Faltan datos para cotizar.'], 422);
        }

        Log::info('Items recibidos', ['items' => $items]);

        // Consolidar ítems y validar stock
        $consolidatedItems = [];
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = (int) $item['quantity'];

            if (!isset($consolidatedItems[$productId])) {
                $consolidatedItems[$productId] = [
                    'product_id' => $productId,
                    'quantity' => 0,
                ];
            }
            $consolidatedItems[$productId]['quantity'] += $quantity;
        }

        $productsToProcess = [];
        $productsWithInsufficientStock = [];

        foreach ($consolidatedItems as $productId => $itemData) {
            $product = Product::find($productId);

            if (!$product) {
                return response()->json([
                    'error' => "Uno o más productos en el carrito no fueron encontrados.",
                    'details' => ['product_id' => $productId, 'message' => 'Producto no existe.']
                ], 404);
            }

            if ($itemData['quantity'] > $product->stock) {
                $productsWithInsufficientStock[] = [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'requested_quantity' => $itemData['quantity'],
                    'available_stock' => $product->stock,
                ];
            } else {
                $productsToProcess[] = [
                    'product_id' => $productId,
                    'quantity' => $itemData['quantity'],
                    'product_details' => $product
                ];
            }
        }

        if (!empty($productsWithInsufficientStock)) {
            Log::warning('Intento de cotización con stock insuficiente', ['details' => $productsWithInsufficientStock]);
            return response()->json([
                'error' => 'Stock insuficiente para algunos productos. Por favor, ajusta las cantidades en tu carrito.',
                'details' => $productsWithInsufficientStock
            ], 400);
        }

        // Procesar y ordenar productos con peso facturable
        $processedItems = collect($productsToProcess)->map(function($item) {
            $product = $item['product_details'];

            $productDetails = [
                'length' => (float) $product->length,
                'width'  => (float) $product->width,
                'height' => (float) $product->height,
                'weight' => (float) $product->weight,
                'id'     => $product->id,
            ];

            $productDetails['volume'] = $this->volumen($productDetails);

            $pesoVolumetricoProducto = $productDetails['volume'] / 5000;

            $productDetails['peso_a_usar'] = max($productDetails['weight'], $pesoVolumetricoProducto);

            if ($productDetails['peso_a_usar'] < 1 && $productDetails['peso_a_usar'] > 0) {
                $productDetails['peso_a_usar'] = 1;
            }

            return [
                'product_details' => $productDetails,
                'quantity' => $item['quantity'],
            ];
        })->filter()
          ->sortByDesc(function($item) {
              return $item['product_details']['volume'];
          })->values()->all();

        Log::info('Items procesados con peso facturable y ordenados', ['items' => $processedItems]);

        $cajas = [
            'chica' => ['length' => 30, 'width' => 20, 'height' => 15, 'volume' => 30*20*15],
            'mediana' => ['length' => 40, 'width' => 35, 'height' => 20, 'volume' => 40*35*20],
            'grande' => ['length' => 50, 'width' => 50, 'height' => 30, 'volume' => 50*50*30],
        ];

        $cajasLlenas = [];

        // UUID para agrupar productos en envios_manual si hay falla
        $pedidoUid = Str::uuid()->toString();

        // Intentar empacar productos en cajas
        foreach ($processedItems as $processedItem) {
            $productToPack = $processedItem['product_details'];

            for ($i = 0; $i < $processedItem['quantity']; $i++) {
                $metido = false;

                // Intentar meter en cajas ya existentes
                foreach ($cajasLlenas as &$caja) {
                    $fittedDimensions = $this->cabeEnCaja($productToPack, $caja);

                    if ($fittedDimensions !== false) {
                        $volumenCaja = $this->volumen($caja);
                        $volumenUsadoEnCaja = array_sum(array_map(fn($p) => $this->volumen($p), $caja['productos']));
                        $volumenProductoActual = $this->volumen($productToPack);

                        $pesoActualEnCaja = $caja['weight'];

                        $boxAlreadyHasProducts = !empty($caja['productos']);
                        $currentProductIsTightFit = false;

                        if (
                            $fittedDimensions[0] > $caja['length'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD ||
                            $fittedDimensions[1] > $caja['width'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD ||
                            $fittedDimensions[2] > $caja['height'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD
                        ) {
                            $currentProductIsTightFit = true;
                        }

                        if (
                            $volumenProductoActual + $volumenUsadoEnCaja <= $volumenCaja * self::VOLUME_FILL_THRESHOLD &&
                            $fittedDimensions[0] <= $caja['length'] &&
                            $fittedDimensions[1] <= $caja['width'] &&
                            $fittedDimensions[2] <= $caja['height']
                        ) {
                            $caja['productos'][] = $productToPack;
                            $caja['weight'] += $productToPack['peso_a_usar'];
                            $metido = true;

                            break;
                        }
                    }
                }
                unset($caja);

                // Si no se metió en cajas existentes, intentar crear nueva caja
                if (!$metido) {
                    $asignado = false;
                    foreach ($cajas as $tipo => $medidasCaja) {
                        $fittedDimensionsNewBox = $this->cabeEnCaja($productToPack, $medidasCaja);

                        if ($fittedDimensionsNewBox !== false) {
                            if (
                                $this->volumen($productToPack) <= $this->volumen($medidasCaja) * self::VOLUME_FILL_THRESHOLD
                            ) {
                                $isTightFitForNewBox = false;
                                if (
                                    $fittedDimensionsNewBox[0] > $medidasCaja['length'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD ||
                                    $fittedDimensionsNewBox[1] > $medidasCaja['width'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD ||
                                    $fittedDimensionsNewBox[2] > $medidasCaja['height'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD
                                ) {
                                    $isTightFitForNewBox = true;
                                }

                                $newBox = [
                                    'tipo' => $tipo,
                                    'length' => $medidasCaja['length'],
                                    'width' => $medidasCaja['width'],
                                    'height' => $medidasCaja['height'],
                                    'weight' => $productToPack['peso_a_usar'],
                                    'productos' => [$productToPack],
                                    'has_tight_fit' => $isTightFitForNewBox
                                ];
                                $cajasLlenas[] = $newBox;
                                $asignado = true;
                                break;
                            }
                        }
                    }

                    if (!$asignado) {
                        // Si no cabe en ninguna caja, crear caja personalizada
                        $customBox = [
                            'tipo' => 'personalizada',
                            'length' => $productToPack['length'],
                            'width' => $productToPack['width'],
                            'height' => $productToPack['height'],
                            'weight' => $productToPack['peso_a_usar'],
                            'productos' => [$productToPack],
                            'has_tight_fit' => true,
                        ];
                        $cajasLlenas[] = $customBox;
                    }
                }
            }
        }

        Log::info('Cajas agrupadas después de la lógica de empaquetado (estado final para cotización)', ['cajas' => $cajasLlenas]);

        $tarifas = [];
        $diasEntregasIndividuales = [];

        // Cotizar cajas con productos agrupados
        foreach ($cajasLlenas as $caja) {
            $pesoFacturableCaja = $caja['weight'];

            if ($pesoFacturableCaja < 1) $pesoFacturableCaja = 1;

            $cotizacion = $this->cotizarCaja($token, $address, $caja, $pesoFacturableCaja);

            if (($cotizacion['error'] ?? false) === true) {
                // Guardar TODO el pedido en envios_manual con pedido_uid
                foreach ($productsToProcess as $producto) {
                    $prod = $producto['product_details'];
                    $cant = $producto['quantity'];

                    DB::table('envios_manual')->insert([
                        'pedido_uid' => $pedidoUid,
                        'user_id'    => $user->id,
                        'address_id' => $address->id,
                        'peso'       => $prod['weight'],
                        'alto'       => $prod['height'],
                        'ancho'      => $prod['width'],
                        'largo'      => $prod['length'],
                        'product_id' => $prod['id'],
                        'cantidad'   => $cant,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                return response()->json([
                    'manual' => true,
                    'message' => 'Se requiere cotizacion manual para este pedido, le llegara a su correo lo mas pronto posible, gracias.',
                    'pedido_uid' => $pedidoUid
                ], 400);
            }

            $tarifas[] = [
                'total' => (float) $cotizacion['total'],
                'days' => (int) ($cotizacion['days'] ?? 0),
                'provider_name' => $cotizacion['provider_name'] ?? 'desconocido',
            ];

            $diasEntregasIndividuales[] = (int) ($cotizacion['days'] ?? 0);
        }

        $maxDiasEntrega = !empty($diasEntregasIndividuales) ? max($diasEntregasIndividuales) : null;

        Log::info('Cajas agrupadas para respuesta final JSON', ['cajas_finales_response' => $cajasLlenas]);

        return response()->json([
            'total_envio' => array_sum(array_column($tarifas, 'total')),
            'cajas_usadas' => $cajasLlenas,
            'tarifas_individuales' => $tarifas,
            'dias_entrega' => $maxDiasEntrega,
        ]);
    }

    private function cotizarCaja($token, $address, $caja, $pesoFacturable)
    {
        $addressFrom = [
            "country_code" => "MX",
            "postal_code" => env('SKYDROPX_ORIGIN_CP'),
            "area_level1" => env('SKYDROPX_ORIGIN_STATE'),
            "area_level2" => env('SKYDROPX_ORIGIN_CITY'),
            "area_level3" => env('SKYDROPX_ORIGIN_COLONY'),
            "street1" => env('SKYDROPX_ORIGIN_STREET'),
            "reference" => env('SKYDROPX_ORIGIN_REFERENCE'),
            "name" => env('SKYDROPX_ORIGIN_NAME'),
            "company" => env('SKYDROPX_ORIGIN_COMPANY', env('SKYDROPX_ORIGIN_NAME')),
            "phone" => env('SKYDROPX_ORIGIN_PHONE'),
            "email" => env('SKYDROPX_ORIGIN_EMAIL')
        ];

        $addressTo = [
            "country_code" => "MX",
            "postal_code" => $address->codigo_postal,
            "area_level1" => $address->estado ?? 'Estado',
            "area_level2" => $address->municipio,
            "area_level3" => $address->colonia,
            "street1" => $address->calle,
            "reference" => $address->indicaciones_entrega ?? "Referencia",
            "name" => $address->nombre_completo ?? "Cliente",
            "company" => "Cliente",
            "phone" => $address->telefono ?? "7777777777",
            "email" => $address->email ?? "cliente@email.com"
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->post('https://api-pro.skydropx.com/api/v1/quotations', [
            "quotation" => [
                "address_from" => $addressFrom,
                "address_to" => $addressTo,
                "parcel" => [
                    "length" => (float) $caja['length'],
                    "width" => (float) $caja['width'],
                    "height" => (float) $caja['height'],
                    "weight" => (float) $pesoFacturable
                ]
            ]
        ]);

        Log::info('Skydropx response inicial', [
            'status' => $response->status(),
            'body' => $response->body(),
            'payload_sent' => [
                "address_from" => $addressFrom,
                "address_to" => $addressTo,
                "parcel" => [
                    "length" => (float) $caja['length'],
                    "width" => (float) $caja['width'],
                    "height" => (float) $caja['height'],
                    "weight" => (float) $pesoFacturable
                ]
            ]
        ]);

        if (!$response->successful()) {
            Log::error('Skydropx error inicial', ['status' => $response->status(), 'body' => $response->body()]);
            return ['error' => true];
        }

        $data = $response->json();

        $reintentos = 0;
        $maxReintentos = 10;
        $tiempoEspera = 2;

        while (($data['is_completed'] ?? false) === false && $reintentos < $maxReintentos) {
            sleep($tiempoEspera);
            $quotationId = $data['id'] ?? '';

            $followUpResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->get("https://api-pro.skydropx.com/api/v1/quotations/" . $quotationId);

            Log::info("Reintento #$reintentos cotización (ID: " . ($data['id'] ?? 'N/A') . ")", [
                'status' => $followUpResponse->status(),
                'body' => $followUpResponse->body()
            ]);

            if (!$followUpResponse->successful()) {
                Log::error('Skydropx error en reintento', ['status' => $followUpResponse->status(), 'body' => $followUpResponse->body(), 'quotation_id' => $data['id'] ?? 'N/A']);
                return ['error' => true];
            }

            $data = $followUpResponse->json();
            $reintentos++;
        }

        if (($data['is_completed'] ?? false) === false) {
            Log::error('Skydropx: Cotización no completada después de varios reintentos.', ['quotation_id' => $data['id'] ?? 'N/A', 'final_data' => $data]);
            return ['error' => true, 'message' => 'Cotización no disponible a tiempo.'];
        }

        Log::info('Cotizacion final exitosa', ['data' => $data]);

        $rate = collect($data['rates'] ?? [])
            ->first(fn($r) => ($r['provider_name'] ?? '') === 'dhl' && !empty($r['total']))
            ?? collect($data['rates'] ?? [])->first(fn($r) => !empty($r['total']));

        return $rate && isset($rate['total'])
            ? [
                'total' => (float) $rate['total'],
                'days' => (int) ($rate['days'] ?? 0),
                'provider_name' => $rate['provider_name'] ?? 'desconocido',
            ]
            : ['error' => true, 'message' => 'No se encontraron tarifas válidas.'];
    }

    private function cabeEnCaja(array $producto, array $caja)
    {
        $dimensiones = [(float) $producto['length'], (float) $producto['width'], (float) $producto['height']];
        $cajaDims = [(float) $caja['length'], (float) $caja['width'], (float) $caja['height']];

        foreach ($this->permutaciones($dimensiones) as $rotada) {
            if (
                $rotada[0] <= $cajaDims[0] &&
                $rotada[1] <= $cajaDims[1] &&
                $rotada[2] <= $cajaDims[2]
            ) {
                return $rotada;
            }
        }
        return false;
    }

    private function permutaciones(array $dim): array
    {
        return [
            [$dim[0], $dim[1], $dim[2]],
            [$dim[0], $dim[2], $dim[1]],
            [$dim[1], $dim[0], $dim[2]],
            [$dim[1], $dim[2], $dim[0]],
            [$dim[2], $dim[0], $dim[1]],
            [$dim[2], $dim[1], $dim[0]],
        ];
    }

    private function volumen(array $dim): float
    {
        return $dim['length'] * $dim['width'] * $dim['height'];
    }
}
