<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category; 
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // Importar para logs

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
        // Reglas de validación
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'price'           => 'required|numeric|min:0',
            'image'           => 'nullable|file|image|max:2048',
            'category_id'     => 'required|exists:categories,id',
            'weight'          => 'required|numeric|min:0.01',
            'height'          => 'required|numeric|min:0.01',
            'width'           => 'required|numeric|min:0.01',
            'length'          => 'required|numeric|min:0.01',
            'stock'           => 'required|integer|min:0',
            'subcategory_id'  => 'required|exists:subcategories,id',
            'gallery.*'       => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            Log::error('ProductController@store: Errores de validación', ['errors' => $validator->errors()]);
            return response()->json($validator->errors(), 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        // Crear el producto
        $product = Product::create([
            'name'           => $request->name,
            'description'    => $request->description,
            'price'          => $request->price,
            'image'          => $imagePath,
            'category_id'    => $request->category_id,
            'weight'         => $request->weight,
            'height'         => $request->height,
            'width'          => $request->width,
            'length'         => $request->length,
            'stock'          => $request->stock,
            'subcategory_id' => $request->subcategory_id,
        ]);

        // 🔽 Agregar múltiples imágenes si se suben
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $imageFile) {
                $path = $imageFile->store('products', 'public');
                $product->images()->create(['image' => $path]);
            }
        }

        Log::info('ProductController@store: Producto creado exitosamente', ['product_id' => $product->id]);

        return response()->json([
            'message' => 'Producto creado exitosamente',
            'product' => $product
        ], 201);
    }

    
    // Mostrar un producto específico
    public function show($id)
    {
        $product = Product::with('images')->find($id);

        if (!$product) {
            Log::warning('ProductController@show: Producto no encontrado', ['product_id' => $id]);
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($product);
    }

    // Actualizar producto
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        // Reglas de validación
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'required|numeric|min:0',
            'image'            => 'nullable|file|image|max:2048',
            'category_id'      => 'required|exists:categories,id',
            'subcategory_id'   => 'required|exists:subcategories,id',
            'weight'           => 'required|numeric|min:0.01',
            'height'           => 'nullable|numeric|min:0.01',
            'width'            => 'nullable|numeric|min:0.01',
            'length'           => 'nullable|numeric|min:0.01',
            'stock'            => 'required|integer|min:0',
            'gallery.*'        => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            Log::error('ProductController@update: Errores de validación', ['errors' => $validator->errors(), 'product_id' => $id]);
            return response()->json($validator->errors(), 422);
        }

        // Imagen principal
        if ($request->hasFile('image')) {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $product->image = $request->file('image')->store('products', 'public');
        }

        // Actualizar campos
        $product->name           = $request->name;
        $product->description    = $request->description;
        $product->price          = $request->price;
        $product->category_id    = $request->category_id;
        $product->subcategory_id = $request->subcategory_id;
        $product->weight         = $request->weight;
        $product->height         = $request->height;
        $product->width          = $request->width;
        $product->length         = $request->length;
        $product->stock          = $request->stock;

        $product->save();

        // 🚨 Agregar imágenes de galería si vienen nuevas
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $galleryImage) {
                $path = $galleryImage->store('products/gallery', 'public');

                $product->images()->create([
                    'image' => $path
                ]);
            }
        }

        Log::info('ProductController@update: Producto actualizado exitosamente', ['product_id' => $product->id]);
        return response()->json([
            'message' => 'Producto actualizado',
            'product' => $product
        ]);
    }


    // Eliminar producto
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
    
        // Elimina la imagen si existe
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }
    
        $product->delete();
    
        Log::info('ProductController@destroy: Producto eliminado', ['product_id' => $id]);
        return response()->json(['message' => 'Producto e imagen eliminados']);
    }
    
    public function byCategory($id)
    {
        $products = Product::where('category_id', $id)->get();
        return response()->json($products);
    }

     // <--- NUEVO MÉTODO: Actualizar solo el estado del producto ---
    public function updateStatus(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $request->validate([
            'status' => 'required|string|in:active,paused,disabled', // Validar que el estado sea uno de los permitidos
        ]);

        $product->status = $request->status;
        $product->save();

        Log::info('ProductController: Estado de producto actualizado', ['product_id' => $id, 'new_status' => $request->status, 'user_id' => auth()->id()]);

        return response()->json(['message' => 'Estado del producto actualizado correctamente.', 'product' => $product]);
    }
}
