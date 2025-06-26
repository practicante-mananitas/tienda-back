<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Obtener reviews de un producto
    public function index($id)
    {
        $reviews = Review::with('user:id,name')
            ->where('product_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews);
    }

    // Crear un nuevo review
    public function store(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $review = Review::create([
            'user_id' => auth()->id(),
            'product_id' => $id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json($review, 201);
    }
}
