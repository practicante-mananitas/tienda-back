<?php

namespace App\Http\Controllers;

use App\Models\CategoryFeaturedProduct;
use Illuminate\Http\Request;

class CategoryFeaturedProductController extends Controller
{
    // Obtener productos destacados para una categoría
   public function index($categoryId)
    {
        $featuredProducts = CategoryFeaturedProduct::with('product')
            ->where('category_id', $categoryId)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id, // ID del destacado
                    'product_id' => $item->product_id,
                    'product' => $item->product,
                ];
            });

        return response()->json($featuredProducts);
    }

    // Agregar producto destacado a una categoría
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'product_id' => 'required|exists:products,id',
        ]);

        $exists = CategoryFeaturedProduct::where('category_id', $request->category_id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'El producto ya está destacado en esta categoría'], 409);
        }

        $featured = CategoryFeaturedProduct::create([
            'category_id' => $request->category_id,
            'product_id' => $request->product_id,
        ]);

        return response()->json($featured, 201);
    }

    // Eliminar producto destacado de una categoría
    public function destroy($id)
    {
        $featured = CategoryFeaturedProduct::findOrFail($id);
        $featured->delete();

        return response()->json(['message' => 'Producto destacado eliminado']);
    }

    public function featuredProducts($categoryId)
    {
        $featuredProducts = CategoryFeaturedProduct::with('product')
            ->where('category_id', $categoryId)
            ->get()
            ->map(function ($item) {
                return $item->product; // solo el producto
            });

        return response()->json($featuredProducts);
    }

}
