<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; // Probablemente también necesites esta para Pedido::factory() si la usas

class Pedido extends Model
{
    use HasFactory; // Si no la tienes, agrégala si usas factories

    protected $fillable = [
        'external_reference',
        'payment_id',
        'status',
        'total',
        'user_id',
        'address_id',
        'envio',              // <--- NUEVO: Asegúrate de añadir 'envio'
        'packaging_details',  // <--- NUEVO: Asegúrate de añadir 'packaging_details'
        'fecha_maxima_entrega',
        'shipment_status', // <--- NUEVO: Estado del envío (in_process, sent, delivered, cancelled)
    ];

    // <--- NUEVO: Propiedad $casts para convertir JSON a array automáticamente --->
    protected $casts = [
        'packaging_details' => 'array', // Esto es CRUCIAL para 'packaging_details'
        'fecha_maxima_entrega' => 'date', // <--- CASTING PARA FECHA
        // Puedes añadir también para fechas si lo necesitas, e.g.:
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
