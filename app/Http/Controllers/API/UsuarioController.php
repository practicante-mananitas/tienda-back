<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\LoginActivity;

class UsuarioController extends Controller
{
    //
    public function cambiarContrasena(Request $request)
    {
        $request->validate([
            'password_actual' => 'required|string',
            'nueva_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/'
            ],
        ]);

        $user = auth()->user();

        if (!Hash::check($request->password_actual, $user->password)) {
            return response()->json(['error' => 'La contraseña actual no es correcta.'], 401);
        }

        // 🔒 Verifica que la nueva no sea igual a la anterior
        if (Hash::check($request->nueva_password, $user->password)) {
            return response()->json(['error' => 'La nueva contraseña no puede ser igual a la anterior.'], 422);
        }

        $user->password = Hash::make($request->nueva_password);
        $user->save();

        return response()->json(['mensaje' => 'Contraseña actualizada correctamente.']);
    }

    public function actividadReciente()
    {
        $user = auth()->user();
        $actividades = $user->loginActivities()->orderBy('login_at', 'desc')->take(10)->get();

        return response()->json($actividades);
    }

    public function sesionesActivas()
    {
        $userId = auth()->id();
        $limiteInactividad = now()->subMinutes(30); // Puedes ajustar el tiempo de inactividad

        $sesiones = \App\Models\LoginActivity::where('user_id', $userId)
            ->whereNull('logout_at')
            ->where('last_activity', '>=', $limiteInactividad)
            ->orderBy('last_activity', 'desc')
            ->get();

        return response()->json($sesiones);
    }

    public function cerrarSesion($id)
    {
        $userId = auth()->id();

        $sesion = \App\Models\LoginActivity::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$sesion) {
            return response()->json(['error' => 'Sesión no encontrada'], 404);
        }

        // Guardar el token de la sesión a cerrar en la blacklist
        $token = $sesion->token;

        if ($token) {
            \App\Models\RevokedToken::create(['token' => $token]);
        }

        // Eliminar sesión en DB
        $sesion->delete();

        return response()->json(['mensaje' => 'Sesión cerrada correctamente']);
    }

}
