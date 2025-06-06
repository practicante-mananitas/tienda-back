<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;


class PedidoController extends Controller
{
    public function ultimo(Request $request)
    {
        $user = $request->user(); // o auth()->user()

        Log::info('🔍 Usuario autenticado en /ultimo-pedido', [
            'user_id' => $user?->id,
            'token' => $request->bearerToken(),
            'headers' => $request->headers->all()
        ]);

        $pedidoId = Cache::get('pedido_user_' . $user->id);

        Log::info('🧾 Último pedido recuperado', [
            'pedido_id' => $pedidoId
        ]);

        if (!$pedidoId) {
            return response()->json(['message' => 'No hay pedido reciente'], 404);
        }

        // Cargar pedido con relaciones necesarias
        $pedido = Pedido::with(['items.product'])->find($pedidoId);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        // Agregar imagen_url a cada item
        foreach ($pedido->items as $item) {
            if ($item->product && $item->product->image) {
                $item->imagen = asset('storage/' . $item->product->image); // 👈 genera la URL real
            } else {
                $item->imagen = '/images/default.jpg';
            }
        }


        Log::info('📦 Pedido completo con dirección e imágenes', [
            'pedido' => $pedido
        ]);

        return response()->json($pedido);
    }

    public function misPedidos(Request $request)
    {
        $user = $request->user(); // asumiendo que usas JWT

        $pedidos = Pedido::with(['items.product'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($pedidos);
    }

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

    public function excedidos()
{
    $pedidos = DB::table('envios_manual')
        ->join('users', 'envios_manual.user_id', '=', 'users.id')
        ->join('addresses', 'envios_manual.address_id', '=', 'addresses.id')
        ->leftJoin('address_extras', 'addresses.id', '=', 'address_extras.address_id')
        ->leftJoin('products', 'envios_manual.product_id', '=', 'products.id')
        ->select(
            'users.name', 'users.email', 'users.phone',
            'addresses.*',
            'address_extras.tipo_lugar', 'address_extras.nombre_casa', 'address_extras.barrio',
            'envios_manual.*',
            'products.name as product_name'
        )
        ->orderBy('envios_manual.created_at', 'desc')
        ->get()
        ->groupBy('user_id');


    return response()->json($pedidos);
}

// PedidoController.php
public function index()
{
    return Pedido::with(['user', 'address'])->orderBy('created_at', 'desc')->get();
}

public function items($id)
{
    return PedidoItem::with('product')
        ->where('pedido_id', $id)
        ->get();
}


}
