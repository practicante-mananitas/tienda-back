<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subcategory;
use Illuminate\Http\Request;


class SubcategoryController extends Controller
{
    // Obtener todas las subcategorías que pertenezcan a una categoría específica
    public function getByCategory($categoryId)
    {
        // Traer las subcategorías que tengan category_id = $categoryId
        $subcategories = Subcategory::where('category_id', $categoryId)->get();

        // Retornar en formato JSON
        return response()->json($subcategories);
    }
}