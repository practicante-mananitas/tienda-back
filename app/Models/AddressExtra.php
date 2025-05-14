<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressExtra extends Model
{
    protected $fillable = [
        'address_id',
        'tipo_lugar',
        'barrio',
        'nombre_casa',
        'conserjeria',
        'hora_apertura',
        'hora_cierre',
        'abierto24',
        'dias',
    ];

    protected $casts = [
        'dias' => 'array',
        'abierto24' => 'boolean',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}

