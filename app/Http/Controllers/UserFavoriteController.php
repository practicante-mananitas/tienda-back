<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserFavoriteController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $favorites = $user->favorites()->get();
        return response()->json($favorites);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $productId = $request->input('product_id');
        $user->favorites()->syncWithoutDetaching([$productId]);
        return response()->json(['message' => 'Producto agregado a favoritos']);
    }

    public function destroy($productId)
    {
        $user = Auth::user();
        $user->favorites()->detach($productId);
        return response()->json(['message' => 'Producto eliminado de favoritos']);
    }

    public function checkFavorite($productId)
    {
        $user = auth()->user();

        // Verificamos si el producto está en favoritos del usuario
        $isFavorite = $user->favorites()->where('product_id', $productId)->exists();

        return response()->json(['isFavorite' => $isFavorite]);
    }

}
