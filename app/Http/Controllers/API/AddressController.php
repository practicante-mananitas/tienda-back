<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Address;
use App\Models\AddressExtra;

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

    public function guardarInfoExtra(Request $request)
{
    $request->validate([
        'address_id' => 'required|exists:addresses,id',
        'tipo_lugar' => 'required|string', // ✅ aquí corregido
        'barrio' => 'nullable|string',
        'nombre_casa' => 'nullable|string',
        'conserjeria' => 'nullable|string',
        'hora_apertura' => 'nullable',
        'hora_cierre' => 'nullable',
        'abierto24' => 'boolean',
        'dias' => 'array'
    ]);

    $extra = AddressExtra::updateOrCreate(
        ['address_id' => $request->address_id], // condición
        [ // valores a crear o actualizar
            'tipo_lugar' => $request->tipo_lugar,
            'barrio' => $request->barrio,
            'nombre_casa' => $request->nombre_casa,
            'conserjeria' => $request->conserjeria,
            'hora_apertura' => $request->hora_apertura,
            'hora_cierre' => $request->hora_cierre,
            'abierto24' => $request->abierto24,
            'dias' => $request->dias,
        ]
    );


    return response()->json(['ok' => true]);
}

public function direccionCompleta($id)
{
    $address = Address::with('extraInfo')->findOrFail($id);

    return response()->json([
        'address' => $address,
        'extra' => $address->extraInfo
    ]);
}

public function index()
{
    $user = auth('api')->user();

    $addresses = Address::with('extraInfo')->where('user_id', $user->id)->get();

    return response()->json($addresses);
}

public function update(Request $request, $id)
{
    $request->validate([
        'calle' => 'required|string',
        'codigo_postal' => 'required',
        'estado' => 'required|string',
        'municipio' => 'required|string',
        'localidad' => 'required|string',
        'colonia' => 'required|string',
        'tipo_domicilio' => 'required|in:residencial,laboral',
    ]);

    $address = Address::findOrFail($id);
    $address->update($request->all());

    return response()->json(['message' => 'Dirección actualizada', 'address' => $address]);
}



}
