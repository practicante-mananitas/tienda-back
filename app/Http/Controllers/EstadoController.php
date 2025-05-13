<?php

namespace App\Http\Controllers;

use App\Models\State;
use App\Models\Municipality;
use Illuminate\Http\Request;

class EstadoController extends Controller
{
    // GET /api/estados
    public function index()
    {
        return response()->json(State::all());
    }

    // GET /api/estados/{id}/municipios
    public function municipios($id)
    {
        $state = State::findOrFail($id);
        return response()->json($state->municipalities);
    }
}
