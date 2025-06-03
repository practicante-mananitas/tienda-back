<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HighlightSection extends Model {
    protected $fillable = ['slug', 'titulo', 'icono'];

    public function productos() {
        return $this->belongsToMany(Product::class, 'highlight_section_product');
    }
}

