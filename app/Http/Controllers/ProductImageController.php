<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    //

public function destroy($id)
{
    $image = ProductImage::find($id);

    if (!$image) {
        return response()->json(['message' => 'Imagen no encontrada'], 404);
    }

    // Eliminar archivo físico si existe
    if (Storage::disk('public')->exists($image->image)) {
        Storage::disk('public')->delete($image->image);
    }

    $image->delete();

    return response()->json(['message' => 'Imagen eliminada']);
}

}
