<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $fillable = [
        'external_reference',
        'payment_id',
        'status',
        'total',
        'user_id',
        'address_id',
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
