<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category_id'];

    // Relación con la categoría (una subcategoría pertenece a una categoría)
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // En App\Models\Subcategory.php

    public function products()
    {
        return $this->hasMany(Product::class);
    }

}
