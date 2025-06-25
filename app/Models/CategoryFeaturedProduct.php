<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryFeaturedProduct extends Model
{
    use HasFactory;

    protected $table = 'category_featured_products';

    protected $fillable = [
        'category_id',
        'product_id',
    ];

    // Relación con categoría
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relación con producto
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
