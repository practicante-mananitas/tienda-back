<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinanzasController extends Controller
{
    //
    public function resumenFinanzas()
{
    $ingresosPorMes = DB::table('pedidos')
        ->select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes"),
            DB::raw("SUM(total) as total_ingresos"),
            DB::raw("SUM(COALESCE(envio, 0)) as ingresos_envio"),
            DB::raw("SUM(total - COALESCE(envio, 0)) as ingresos_productos"),
            DB::raw("COUNT(*) as cantidad_pedidos")
        )
        ->where('status', 'approved')
        ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
        ->orderBy('mes', 'desc')
        ->get();

    $totales = DB::table('pedidos')
        ->selectRaw('
            SUM(total) as total_general,
            SUM(COALESCE(envio, 0)) as total_envio,
            SUM(total - COALESCE(envio, 0)) as total_productos
        ')
        ->where('status', 'approved')
        ->first();

    return response()->json([
        'resumen_general' => [
            'total' => $totales->total_general,
            'envio' => $totales->total_envio,
            'productos' => $totales->total_productos,
        ],
        'ingresos_por_mes' => $ingresosPorMes
    ]);
}

}
