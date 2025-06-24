<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Corregido el namespace
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'category_id',
        'weight',
        'height',
        'width',
        'length',
        'stock',
        'status',
        'subcategory_id',
    ];

        protected $casts = [
        'price' => 'decimal:2', // Asegura que el precio se maneje como decimal
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'width' => 'decimal:2',
        'length' => 'decimal:2',
        'stock' => 'integer', // <--- CASTING PARA STOCK
        'status' => 'string', // Castear a string (aunque es el default, es buena práctica)
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function highlightSections() 
    {
        return $this->belongsToMany(HighlightSection::class, 'highlight_section_product');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

}
