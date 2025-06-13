<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Log; // Importar Log para depuración

class CartController extends Controller
{
    /**
     * Muestra los ítems del carrito del usuario autenticado.
     * Carga la relación 'product' para obtener los detalles completos del producto.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $items = $cart->items()->with('product')->get(); // Cargar detalles completos del producto

        return response()->json($items);
    }

    /**
     * Agrega un producto al carrito o actualiza su cantidad si ya existe,
     * validando el stock disponible.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1'
        ]);

        $user = $request->user();
        $productId = $request->product_id;
        $quantityToAdd = $request->quantity ?? 1;

        // 1. Obtener el producto para verificar stock
        $product = Product::find($productId);
        if (!$product) {
            Log::warning('CartController@add: Producto no encontrado', ['product_id' => $productId, 'user_id' => $user->id]);
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        // 2. Verificar stock inicial
        if ($product->stock <= 0) {
            Log::info('CartController@add: Producto agotado', ['product_id' => $productId, 'user_id' => $user->id]);
            return response()->json(['message' => 'Este producto está agotado y no puede ser añadido al carrito.'], 400);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        // Buscar si el producto ya existe en el carrito
        $cartItem = CartItem::where('cart_id', $cart->id)
                            ->where('product_id', $productId)
                            ->first();

        $message = '';
        if ($cartItem) {
            // Si el producto ya está en el carrito, actualiza su cantidad
            $newQuantity = $cartItem->quantity + $quantityToAdd;
            
            // 3. Validar la nueva cantidad contra el stock disponible
            if ($newQuantity > $product->stock) {
                // Ajustar la cantidad a la disponible
                $cartItem->quantity = $product->stock;
                $message = "Se añadió la cantidad máxima posible de \"{$product->name}\" al carrito. Stock disponible: {$product->stock}.";
                Log::info('CartController@add: Cantidad ajustada por stock (ya en carrito)', [
                    'product_id' => $productId,
                    'requested' => $newQuantity,
                    'available' => $product->stock,
                    'final_quantity' => $cartItem->quantity
                ]);
            } else {
                $cartItem->quantity = $newQuantity;
                $message = "Cantidad de \"{$product->name}\" actualizada en el carrito.";
                Log::info('CartController@add: Cantidad actualizada en carrito', [
                    'product_id' => $productId,
                    'new_quantity' => $newQuantity,
                    'user_id' => $user->id
                ]);
            }
            $cartItem->save(); // Guarda el ítem del carrito actualizado

        } else {
            // Si el producto no está en el carrito, lo añade como nuevo ítem
            $finalQuantity = $quantityToAdd;
            // 3. Validar la cantidad inicial contra el stock disponible
            if ($finalQuantity > $product->stock) {
                $finalQuantity = $product->stock;
                $message = "Solo se pudo añadir la cantidad disponible de \"{$product->name}\" al carrito. Stock: {$product->stock}.";
                Log::info('CartController@add: Cantidad ajustada por stock (nuevo ítem)', [
                    'product_id' => $productId,
                    'requested' => $quantityToAdd,
                    'available' => $product->stock,
                    'final_quantity' => $finalQuantity
                ]);
            }

            // Asegurarse de que la cantidad final no sea cero si se está añadiendo
            if ($finalQuantity <= 0) {
                return response()->json(['message' => 'No se pudo añadir el producto al carrito (cantidad inválida o agotado).'], 400);
            }

            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $finalQuantity,
            ]);
            $message = "Producto \"{$product->name}\" agregado al carrito.";
            Log::info('CartController@add: Producto agregado como nuevo', [
                'product_id' => $productId,
                'quantity' => $finalQuantity,
                'user_id' => $user->id
            ]);
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Elimina una unidad de un producto del carrito, o lo elimina completamente si la cantidad llega a cero.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function remove(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->first();

        if (!$cart) {
            Log::warning('CartController@remove: Carrito no encontrado para usuario', ['user_id' => $request->user()->id]);
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        $item = $cart->items()->where('product_id', $request->product_id)->first();

        if ($item) {
            $item->quantity--;
            if ($item->quantity <= 0) {
                $item->delete();
                Log::info('CartController@remove: Producto eliminado del carrito', ['product_id' => $item->product_id, 'user_id' => $request->user()->id]);
                return response()->json(['message' => 'Producto eliminado del carrito.']);
            } else {
                $item->save();
                Log::info('CartController@remove: Cantidad de producto decremental en carrito', ['product_id' => $item->product_id, 'new_quantity' => $item->quantity, 'user_id' => $request->user()->id]);
                return response()->json(['message' => 'Cantidad de producto actualizada en el carrito.']);
            }
        }
        Log::warning('CartController@remove: Producto no encontrado en carrito para eliminar', ['product_id' => $request->product_id, 'user_id' => $request->user()->id]);
        return response()->json(['message' => 'Producto no encontrado en el carrito.'], 404);
    }

    /**
     * Vacía completamente el carrito del usuario.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $cart->items()->delete();
            Log::info('CartController@clear: Carrito vaciado', ['user_id' => $request->user()->id]);
            return response()->json(['message' => 'Carrito vaciado.']);
        }
        Log::warning('CartController@clear: Intento de vaciar carrito no existente', ['user_id' => $request->user()->id]);
        return response()->json(['message' => 'Carrito no encontrado o ya vacío.'], 404);
    }

    /**
     * Actualiza la cantidad de un producto específico en el carrito.
     * Puede ser útil si el frontend permite editar la cantidad directamente.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function updateQuantity(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0' // Permitir 0 para eliminar el ítem
        ]);

        $user = $request->user();
        $productId = $request->product_id;
        $newQuantity = (int) $request->quantity;

        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cartItem = CartItem::where('cart_id', $cart->id)
                            ->where('product_id', $productId)
                            ->first();

        if ($newQuantity === 0) {
            if ($cartItem) {
                $cartItem->delete();
                Log::info('CartController@updateQuantity: Producto eliminado por cantidad 0', ['product_id' => $productId, 'user_id' => $user->id]);
                return response()->json(['message' => 'Producto eliminado del carrito.']);
            }
            return response()->json(['message' => 'Producto no encontrado en el carrito para eliminar.'], 404);
        }

        if ($newQuantity > $product->stock) {
            $finalQuantity = $product->stock;
            $message = "No hay suficiente stock de \"{$product->name}\". Cantidad ajustada a {$finalQuantity}.";
            Log::info('CartController@updateQuantity: Cantidad ajustada por stock', [
                'product_id' => $productId,
                'requested' => $newQuantity,
                'available' => $product->stock,
                'final_quantity' => $finalQuantity
            ]);
        } else {
            $finalQuantity = $newQuantity;
            $message = "Cantidad de \"{$product->name}\" actualizada.";
            Log::info('CartController@updateQuantity: Cantidad actualizada', [
                'product_id' => $productId,
                'new_quantity' => $finalQuantity,
                'user_id' => $user->id
            ]);
        }

        if ($cartItem) {
            $cartItem->quantity = $finalQuantity;
            $cartItem->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $finalQuantity,
            ]);
            $message = "Producto \"{$product->name}\" agregado al carrito con la cantidad especificada.";
            Log::info('CartController@updateQuantity: Producto agregado', [
                'product_id' => $productId,
                'quantity' => $finalQuantity,
                'user_id' => $user->id
            ]);
        }

        return response()->json(['message' => $message]);
    }
}
