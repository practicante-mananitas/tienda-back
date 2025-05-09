<?php

namespace App\Models;
use App\Models\User;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    //
    protected $fillable = [
        'user_id',
        'calle',
        'numero_interior',
        'codigo_postal',
        'estado',
        'municipio',
        'localidad',
        'colonia',
        'tipo_domicilio',
        'indicaciones_entrega',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
