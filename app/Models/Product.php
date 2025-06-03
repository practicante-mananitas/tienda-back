<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
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
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function highlightSections() 
    {
        return $this->belongsToMany(HighlightSection::class, 'highlight_section_product');
    }

}
