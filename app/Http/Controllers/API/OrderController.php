<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Order;   
use App\Models\OrderItem;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function store(Request $request)
{
    $user = Auth::user();
    $cart = $user->cart()->with('items.product')->first();

    if (!$cart || $cart->items->isEmpty()) {
        return response()->json(['message' => 'El carrito está vacío.'], 400);
    }

    $order = Order::create([
        'user_id' => $user->id,
        'total' => $cart->items->sum(fn($item) => $item->product->price * $item->quantity),
    ]);

    foreach ($cart->items as $item) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item->product->id,
            'quantity' => $item->quantity,
            'price' => $item->product->price,
        ]);
    }

    // Vaciar carrito
    $cart->items()->delete();

    return response()->json([
        'message' => 'Pedido realizado con éxito.',
        'order' => [
            'total' => $order->total,
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price
                ];
            })
        ]
    ]);    
}

public function myOrders()
{
    $user = Auth::user();
    $orders = $user->orders()->with('items.product')->latest()->get();

    return response()->json($orders);
}

public function repeat($orderId)
{
    $user = Auth::user();
    $order = $user->orders()->with('items')->findOrFail($orderId);
    $cart = $user->cart()->firstOrCreate([]);

    foreach ($order->items as $item) {
        $cart->items()->create([
            'product_id' => $item->product_id,
            'quantity' => $item->quantity
        ]);
    }

    return response()->json(['message' => 'Pedido agregado al carrito']);
}

}
