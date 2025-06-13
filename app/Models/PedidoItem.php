<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // Corregido: de Illuminate->Database a Illuminate\Database
use Illuminate\Database\Eloquent\Factories\HasFactory; // Corregido: de Illuminate->Database a Illuminate\Database

class PedidoItem extends Model
{
    use HasFactory; // Agrega esta línea si usas factories para PedidoItem

    protected $fillable = [
        'pedido_id',
        // 'producto', // <--- ELIMINA ESTA LÍNEA si no tienes una columna 'producto' en tu DB,
                    //      o si no la usas para almacenar un JSON del producto.
                    //      Normalmente, usas 'product_id' y la relación product().
        'product_id',       // <--- ASEGÚRATE DE QUE ESTA ES LA COLUMNA CORRECTA DE LA LLAVE FORÁNEA
        'cantidad',
        'precio_unitario',
    ];

    // Puedes añadir casts para fechas si lo necesitas
    protected $casts = [
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // Si 'producto' fuera una columna JSON, NECESITARÍAS ESTO:
        // 'producto' => 'array',
    ];


    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
