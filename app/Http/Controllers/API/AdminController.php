<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pedido;          // Importar el modelo Pedido
use App\Models\PedidoItem;      // Importar el modelo PedidoItem (si mantienes el método items())
use App\Models\Address;         // Importar el modelo Address
use App\Models\AddressExtra;    // Importar el modelo AddressExtra (si tienes esta tabla y relación)
use Illuminate\Support\Facades\Log; // Importar la fachada Log

class AdminController extends Controller
{
    /**
     * Obtiene una lista paginada de todos los pedidos, incluyendo el usuario y la dirección asociada.
     * Los pedidos se ordenan por fecha de creación descendente.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // CAMBIO CLAVE: Añadir una condición where para filtrar por status 'approved'
        return Pedido::with(['user', 'address'])
                      ->where('status', 'approved') // <--- FILTRO AGREGADO AQUÍ
                      ->orderBy('created_at', 'desc')
                      ->get();
    }
    /**
     * Obtiene los ítems de un pedido específico.
     * Este método podría ser redundante si showPedidoDetails() ya obtiene todos los ítems.
     * Considera si aún lo necesitas.
     *
     * @param int $id El ID del pedido.
     * @return \Illuminate\Http\JsonResponse
     */
    public function items($id)
    {
        return PedidoItem::with('product')
            ->where('pedido_id', $id)
            ->get();
    }

    /**
     * Obtiene todos los detalles completos de un pedido específico.
     * Incluye usuario, dirección completa (con sus extras), ítems del pedido (con sus productos)
     * y los detalles de empaquetado.
     *
     * @param int $id El ID del pedido.
     * @return \Illuminate\Http\JsonResponse
     */
    public function showPedidoDetails($id)
    {
        // Carga el pedido con todas las relaciones anidadas necesarias para el modal.
        // CAMBIO AQUÍ: 'address.extraInfo' en lugar de 'address.address_extras'
        // Esto coincide con el nombre de la función de relación en tu modelo Address.php
        $pedido = Pedido::with([
            'user',
            'address.extraInfo', // <--- ¡CORREGIDO! Usando 'extraInfo' para la relación
            'items.product'      // Carga los ítems del pedido y sus productos
        ])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // Si 'packaging_details' no está casteado a 'array' en el modelo Pedido,
        // es posible que llegue como un string JSON. Si ya está casteado, esta línea no es necesaria.
        // Lo mantengo aquí como un "seguro" por si el cast no funciona o no está definido.
        if (isset($pedido->packaging_details) && is_string($pedido->packaging_details)) {
            $pedido->packaging_details = json_decode($pedido->packaging_details, true);
        }

        // \Log::info('Detalles del Pedido para Admin:', ['pedido' => $pedido->toArray()]); // Para depuración

        return response()->json($pedido);
    }

    public function productosPorCategorianuevo()
{
    $datos = DB::table('productos')
        ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
        ->select('categorias.nombre as categoria', DB::raw('count(*) as total'))
        ->groupBy('categoria')
        ->get();

    return response()->json($datos);
}

}
