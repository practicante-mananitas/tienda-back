<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;  // <-- Agrega esta línea
use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Product;

class AdminResumenController extends Controller
{
    // Pedidos pendientes
    public function pedidosPendientes()
    {
        $pendientes = Pedido::where('shipment_status', 'in_process')->get();
        return response()->json([
            'count' => $pendientes->count(),
            'pedidos' => $pendientes,
        ]);
    }

    // Productos con poco stock (menos de 10)
    public function productosBajoStock()
    {
        $productos = Product::where('stock', '<', 10)->get();
        return response()->json([
            'count' => $productos->count(),
            'productos' => $productos,
        ]);
    }

    // Pedidos retrasados (en proceso > 5 días, ejemplo)
    public function pedidosRetrasados()
    {
        $fechaLimite = now()->subDays(5);
        $retrasados = Pedido::where('shipment_status', 'in_process')
            ->where('created_at', '<', $fechaLimite)
            ->get();
        return response()->json([
            'count' => $retrasados->count(),
            'pedidos' => $retrasados,
        ]);
    }
}
