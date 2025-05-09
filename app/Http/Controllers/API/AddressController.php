<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Address;

class AddressController extends Controller
{
    //
    public function store(Request $request)
{
    $request->validate([
        'calle' => 'required|string',
        'codigo_postal' => 'required',
        'estado' => 'required|string',
        'municipio' => 'required|string',
        'localidad' => 'required|string',
        'colonia' => 'required|string',
        'tipo_domicilio' => 'required|in:residencial,laboral',
        // otros campos opcionales aquí
    ]);

    $user = auth('api')->user();

    $address = Address::create([
        'user_id' => $user->id,
        'calle' => $request->calle,
        'numero_interior' => $request->numero_interior,
        'codigo_postal' => $request->codigo_postal,
        'estado' => $request->estado,
        'municipio' => $request->municipio,
        'localidad' => $request->localidad,
        'colonia' => $request->colonia,
        'tipo_domicilio' => $request->tipo_domicilio,
        'indicaciones_entrega' => $request->indicaciones_entrega,
    ]);

    return response()->json(['message' => 'Dirección guardada', 'address' => $address]);
}

}
