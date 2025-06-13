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

class ShippingController extends Controller
{
    protected $skydropx;

    // Define un umbral de llenado de volumen para un empaquetado más "conveniente"
    // No llenar la caja más allá de este porcentaje de su volumen total.
    // Se ha ajustado a 80% para un empaquetado más realista.
    const VOLUME_FILL_THRESHOLD = 0.80; // 80% del volumen total de la caja

    // Define un umbral para considerar que un producto ocupa una "dimensión crítica" de la caja.
    // Si una dimensión del producto (ya rotado para caber) es > este % de la dimensión de la caja,
    // se considera un "ajuste apretado" que dificulta añadir más items a una caja ya iniciada.
    const MAJOR_DIMENSION_UTILIZATION_THRESHOLD = 0.85; // 85%

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

        // --- NUEVO PASO: Consolidar ítems y validar stock ---
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

        $productsToProcess = []; // Aquí guardaremos los items ya validados con la cantidad total
        $productsWithInsufficientStock = [];

        foreach ($consolidatedItems as $productId => $itemData) {
            $product = Product::find($productId);

            if (!$product) {
                // Manejar caso donde el producto no existe (ej. fue eliminado)
                return response()->json([
                    'error' => "Uno o más productos en el carrito no fueron encontrados.",
                    'details' => ['product_id' => $productId, 'message' => 'Producto no existe.']
                ], 404);
            }

            // Validar si la cantidad total solicitada es mayor que el stock disponible
            if ($itemData['quantity'] > $product->stock) {
                $productsWithInsufficientStock[] = [
                    'product_id' => $productId,
                    'product_name' => $product->name, // Asume que el modelo Product tiene un campo 'name'
                    'requested_quantity' => $itemData['quantity'],
                    'available_stock' => $product->stock,
                ];
            } else {
                // Si hay suficiente stock, añadir a la lista para procesamiento posterior
                $productsToProcess[] = [
                    'product_id' => $productId,
                    'quantity' => $itemData['quantity'],
                    'product_details' => $product // Pasa el objeto Product completo para evitar re-consultas
                ];
            }
        }

        if (!empty($productsWithInsufficientStock)) {
            Log::warning('Intento de cotización con stock insuficiente', ['details' => $productsWithInsufficientStock]);
            return response()->json([
                'error' => 'Stock insuficiente para algunos productos. Por favor, ajusta las cantidades en tu carrito.',
                'details' => $productsWithInsufficientStock
            ], 400); // Usamos 400 Bad Request o 422 Unprocessable Entity
        }
        // --- FIN: Consolidar ítems y validar stock ---

        // --- Paso 1: Procesar y ordenar los productos, calculando el peso facturable ---
        // Ahora usamos $productsToProcess que ya tiene los ítems consolidados y validados
        $processedItems = collect($productsToProcess)->map(function($item) {
            $product = $item['product_details']; // Ya tenemos el objeto Product cargado

            $productDetails = [
                'length' => (float) $product->length,
                'width'  => (float) $product->width,
                'height' => (float) $product->height,
                'weight' => (float) $product->weight, // Peso real del producto
                'id'     => $product->id,
            ];

            $productDetails['volume'] = $this->volumen($productDetails);
            
            $pesoVolumetricoProducto = $productDetails['volume'] / 5000;

            $productDetails['peso_a_usar'] = max($productDetails['weight'], $pesoVolumetricoProducto);
            
            // Opcional: Asegura que el peso mínimo facturable por unidad sea 1kg si es menor a 1kg y mayor a 0.
            if ($productDetails['peso_a_usar'] < 1 && $productDetails['peso_a_usar'] > 0) {
                $productDetails['peso_a_usar'] = 1; 
            }
            
            return [
                'product_details' => $productDetails,
                'quantity' => $item['quantity'], // Usamos la cantidad consolidada
            ];
        })->filter() // Filtra cualquier null si Product::find no encontrara algo (ya lo manejamos arriba, pero buena práctica)
          ->sortByDesc(function($item) {
            return $item['product_details']['volume'];
        })->values()->all();

        Log::info('Items procesados con peso facturable y ordenados', ['items' => $processedItems]);

        // Definición de los tipos de cajas disponibles con sus dimensiones y volumen precalculado
        $cajas = [
            'chica' => ['length' => 30, 'width' => 20, 'height' => 15, 'volume' => 30*20*15],
            'mediana' => ['length' => 40, 'width' => 35, 'height' => 20, 'volume' => 40*35*20],
            'grande' => ['length' => 50, 'width' => 50, 'height' => 30, 'volume' => 50*50*30],
        ];

        $cajasLlenas = [];
        
        // --- Paso 2: Empaquetar cada producto individualmente en las cajas ---
        // Se itera a través de cada producto en el orden ya definido (por volumen descendente).
        foreach ($processedItems as $processedItem) {
            $productToPack = $processedItem['product_details']; 

            for ($i = 0; $i < $processedItem['quantity']; $i++) { // Itera por la cantidad total consolidada
                $metido = false;

                // --- Intenta meter el producto en una caja existente ---
                // Se itera sobre las cajas ya existentes para ver si el producto actual cabe.
                foreach ($cajasLlenas as &$caja) { // Usamos & para modificar la caja original en el array
                    $fittedDimensions = $this->cabeEnCaja($productToPack, $caja);

                    if ($fittedDimensions !== false) { // Si el producto cabe dimensionalmente en esta caja
                        $volumenCaja = $this->volumen($caja);
                        $volumenUsadoEnCaja = array_sum(array_map(fn($p) => $this->volumen($p), $caja['productos']));
                        $volumenProductoActual = $this->volumen($productToPack);
                        
                        $pesoActualEnCaja = $caja['weight'];
                        $pesoTotalConNuevoProducto = $pesoActualEnCaja + $productToPack['peso_a_usar'];

                        $boxAlreadyHasProducts = !empty($caja['productos']);
                        $currentProductIsTightFit = false;

                        // Se verifica si el producto actual (en su orientación ajustada) usa una "dimensión crítica" de la caja
                        if (
                            $fittedDimensions[0] > $caja['length'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD ||
                            $fittedDimensions[1] > $caja['width'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD ||
                            $fittedDimensions[2] > $caja['height'] * self::MAJOR_DIMENSION_UTILIZATION_THRESHOLD
                        ) {
                            $currentProductIsTightFit = true;
                        }
                        
                        // Criterios para "convenientemente caber" en una caja existente:
                        // 1. El producto cabe dimensionalmente (ya verificado por $fittedDimensions !== false).
                        // 2. El volumen total (productos existentes + nuevo producto) no excede el umbral de llenado.
                        // 3. El peso total de la caja con el nuevo producto no excede el límite de 20 kg.
                        // 4. Y MUY IMPORTANTE: Evitar colocar un producto que genera un "ajuste apretado"
                        //    en una caja que *ya contiene otros productos*. Si la caja está vacía,
                        //    un "ajuste apretado" es aceptable, ya que podría ser el único artículo grande.
                        if (
                            ($volumenProductoActual + $volumenUsadoEnCaja <= $volumenCaja * self::VOLUME_FILL_THRESHOLD) &&
                            ($pesoTotalConNuevoProducto <= 20) &&
                            !($boxAlreadyHasProducts && $currentProductIsTightFit)
                        ) {
                            $caja['productos'][] = $productToPack;
                            $caja['weight'] += $productToPack['peso_a_usar'];
                            $metido = true;
                            // Si el producto que acabamos de añadir genera un "ajuste apretado",
                            // marcamos la caja para que futuros intentos de añadir más items sean más estrictos.
                            if ($currentProductIsTightFit) {
                                $caja['has_tight_fit'] = true;
                            }
                            break; // El producto ha sido metido, se pasa al siguiente producto
                        }
                    }
                }

                // --- Si el producto no se pudo meter en una caja existente, intenta crear una nueva ---
                if (!$metido) {
                    $asignado = false;
                    foreach ($cajas as $tipo => $medidasCaja) {
                        $fittedDimensionsNewBox = $this->cabeEnCaja($productToPack, $medidasCaja);

                        if ($fittedDimensionsNewBox !== false) {
                            if ($productToPack['peso_a_usar'] <= 20 && 
                                $this->volumen($productToPack) <= $this->volumen($medidasCaja) * self::VOLUME_FILL_THRESHOLD) {
                                
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
                                    'weight' => $productToPack['peso_a_usar'], // El peso inicial de la nueva caja es el peso facturable del producto
                                    'productos' => [$productToPack], // Se agrega el producto con sus dimensiones y peso facturable
                                    'has_tight_fit' => $isTightFitForNewBox // Se marca si el primer producto en la nueva caja es un ajuste apretado
                                ];
                                $cajasLlenas[] = $newBox;
                                $asignado = true;
                                break;
                            }
                        }
                    }

                    // Si el producto aún no se pudo asignar, se requiere cotización manual
                    if (!$asignado) {
                        DB::table('envios_manual')->insert([
                            'user_id'    => $user->id,
                            'address_id' => $address->id,
                            'peso'       => $productToPack['weight'], 
                            'alto'       => $productToPack['height'],
                            'ancho'      => $productToPack['width'],
                            'largo'      => $productToPack['length'],
                            'product_id' => $productToPack['id'],
                            'cantidad'   => 1, // Se registra como 1 porque es una unidad la que no cupo
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        return response()->json([
                            'manual' => true,
                            'message' => 'Un producto no cabe en ninguna caja estándar o no se puede empacar convenientemente. Se requiere cotización manual.'
                        ], 400);
                    }
                }
            }
        }
        // !!! IMPORTANTE: Destruye la referencia ($caja) después del bucle con '&' !!!
        unset($caja); 

        Log::info('Cajas agrupadas después de la lógica de empaquetado (estado final para cotización)', ['cajas' => $cajasLlenas]);

        // --- Paso 3: Cotizar cada caja con Skydropx ---
        $tarifas = [];
        $diasEntregasIndividuales = []; // Inicialización del array para almacenar los días de entrega de cada caja

        foreach ($cajasLlenas as $caja) {
            $pesoFacturableCaja = $caja['weight']; 

            if ($pesoFacturableCaja < 1) $pesoFacturableCaja = 1;

            $cotizacion = $this->cotizarCaja($token, $address, $caja, $pesoFacturableCaja); 
            if ($cotizacion['error'] ?? false) {
                Log::error('Error al cotizar una caja con Skydropx. Respuesta de cotizarCaja: ', ['cotizacion_error' => $cotizacion]);
                return response()->json(['error' => 'Error al cotizar una caja con Skydropx.'], 500);
            }
            $tarifas[] = (float) $cotizacion['total'];
            $diasEntregasIndividuales[] = (int) ($cotizacion['days'] ?? 0); // Almacena los días de entrega de la cotización individual
        }

        // Calcula el máximo de días de entrega entre todas las cajas
        // Si no hay días (e.g., todas las cotizaciones fallaron), devuelve null.
        $maxDiasEntrega = !empty($diasEntregasIndividuales) ? max($diasEntregasIndividuales) : null;

        // --- Paso 4: Devolver la respuesta final ---
        Log::info('Cajas agrupadas para respuesta final JSON', ['cajas_finales_response' => $cajasLlenas]);

        return response()->json([
            'total_envio' => array_sum($tarifas),
            'cajas_usadas' => $cajasLlenas,
            'tarifas_individuales' => $tarifas,
            'dias_entrega' => $maxDiasEntrega, // Incluye los días de entrega en la respuesta final
        ]);
    }

    /**
     * Realiza una cotización para una caja específica con Skydropx.
     *
     * @param string $token Token de autenticación de Skydropx.
     * @param \App\Models\Address $address Dirección de destino.
     * @param array $caja Datos de la caja (dimensiones y peso).
     * @param float $pesoFacturable Peso facturable de la caja (el que se envía a Skydropx).
     * @return array Array con el total de la cotización y los días de entrega, o un indicador de error.
     */
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

        // Asegúrate de retornar los días de entrega de la tarifa seleccionada
        return $rate && isset($rate['total']) 
            ? ['total' => (float) $rate['total'], 'days' => (int) ($rate['days'] ?? 0)] 
            : ['error' => true, 'message' => 'No se encontraron tarifas válidas.'];
    }

    /**
     * Determina si un producto puede caber en una caja, considerando todas sus posibles rotaciones.
     * Retorna el array de dimensiones rotadas si cabe, o false si no.
     *
     * @param array $producto Arreglo con 'length', 'width', 'height' del producto.
     * @param array $caja Datos de la caja con 'length', 'width', 'height'.
     * @return array|false Array de dimensiones rotadas (largo, ancho, alto) si cabe, false en caso contrario.
     */
    private function cabeEnCaja(array $producto, array $caja)
    {
        $dimensiones = [(float) $producto['length'], (float) $producto['width'], (float) $producto['height']];
        $cajaDims = [(float) $caja['length'], (float) $caja['width'], (float) $caja['height']];

        foreach ($this->permutaciones($dimensiones) as $rotada) {
            // Se asegura que las dimensiones del producto rotado no excedan las de la caja
            if (
                $rotada[0] <= $cajaDims[0] &&
                $rotada[1] <= $cajaDims[1] &&
                $rotada[2] <= $cajaDims[2]
            ) {
                return $rotada; // Retorna las dimensiones rotadas que sí caben
            }
        }
        return false; // No cabe en ninguna orientación
    }

    /**
     * Genera todas las 6 posibles permutaciones de las dimensiones de un objeto 3D.
     * Esto permite verificar si un objeto cabe en un espacio al rotarlo.
     *
     * @param array $dim Array de 3 dimensiones [largo, ancho, alto].
     * @return array Array de arrays, cada uno representando una permutación de las dimensiones.
     */
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

    /**
     * Calcula el volumen de un objeto (producto o caja) dadas sus dimensiones.
     *
     * @param array $dim Array con 'length', 'width', 'height'.
     * @return float El volumen calculado.
     */
    private function volumen(array $dim): float
    {
        return (float) $dim['length'] * (float) $dim['width'] * (float) $dim['height'];
    }
}
