<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;


class CartController extends Controller
{
    //
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $items = $cart->items()->with('product')->get();

        return response()->json($items);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1'
        ]);

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $item = $cart->items()->firstOrCreate(
            ['product_id' => $request->product_id],
            ['quantity' => 0]
        );

        $item->quantity += $request->quantity ?? 1;
        $item->save();

        return response()->json(['message' => 'Producto agregado al carrito']);
    }

    public function remove(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->first();

        if (!$cart) return response()->json(['message' => 'Carrito no encontrado'], 404);

        $item = $cart->items()->where('product_id', $request->product_id)->first();

        if ($item) {
            $item->quantity--;
            if ($item->quantity <= 0) {
                $item->delete();
            } else {
                $item->save();
            }
        }

        return response()->json(['message' => 'Producto actualizado']);
    }

    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json(['message' => 'Carrito vaciado']);
    }

}
