<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SoporteController extends Controller
{
    //
    // app/Http/Controllers/SoporteController.php

    public function enviarConsulta(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string',
            'correo' => 'required|email',
            'mensaje' => 'required|string',
        ]);

        Mail::raw("Nombre: {$data['nombre']}\nCorreo: {$data['correo']}\nMensaje: {$data['mensaje']}", function($message) use ($data) {
            $message->to('soporte@tuempresa.com')
                    ->subject('Nueva consulta de soporte');
        });

        return response()->json(['message' => 'Consulta enviada correctamente']);
    }

}
