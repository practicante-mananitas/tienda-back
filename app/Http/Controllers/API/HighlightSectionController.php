<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\HighlightSection;

class HighlightSectionController extends Controller
{
    //
    public function index()
{
    return HighlightSection::with('productos')->get();
}

public function sync(Request $request)
{
    $map = [
        'top-vendidos' => $request->top_vendidos,
        'recomendados' => $request->recomendados,
        'ofertas' => $request->ofertas,
    ];

    foreach ($map as $slug => $ids) {
        $seccion = HighlightSection::where('slug', $slug)->first();
        if ($seccion) $seccion->productos()->sync($ids);
    }

    return response()->json(['message' => 'Actualizado']);
}

}
