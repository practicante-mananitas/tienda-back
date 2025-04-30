<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // Listar todos los productos
    public function index()
    {
        return response()->json(Product::all());
    }

    // Crear un nuevo producto
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric',
            'image'       => 'nullable|file|image|max:2048' // acepta imagen
        ]);
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        // Guardar imagen si viene
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }
    
        $product = Product::create([
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'image'       => $imagePath
        ]);
    
        return response()->json([
            'message' => 'Producto creado exitosamente',
            'product' => $product
        ], 201);
    }
    

    // Mostrar un producto específico
    public function show($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($product);
    }

    // Actualizar producto
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
    
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'image' => 'nullable|file|image|max:2048'
        ]);
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        // Subir imagen si viene nueva
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $product->image = $imagePath;
        }
    
        // Actualizar el resto
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
    
        $product->save();
    
        return response()->json(['message' => 'Producto actualizado', 'product' => $product]);
    }
    

    // Eliminar producto
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
    
        // 👇 Elimina la imagen si existe
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }
    
        $product->delete();
    
        return response()->json(['message' => 'Producto e imagen eliminados']);
    }
    
    
}
