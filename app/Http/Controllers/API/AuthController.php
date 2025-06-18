<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\LoginActivity;
use Illuminate\Support\Facades\Http; // arriba en el archivo
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'role' => 'user', // <- aquí lo forzamos
        ]);

        return response()->json(['message' => 'Usuario registrado exitosamente', 'user' => $user]);
    }



    public function login(Request $request)
    {
        \Log::info('Login iniciado para email: ' . $request->email);
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        $user = JWTAuth::setToken($token)->toUser();
        $ip = $request->ip();

        // 🧪 Simulación si estás en localhost
        if ($ip === '127.0.0.1' || $ip === '::1') {
            $location = 'Cuernavaca, Morelos, México';
        } else {
            $location = null;
            try {
                $response = Http::timeout(3)->get("https://ipapi.co/{$ip}/json/");
                if ($response->successful()) {
                    $data = $response->json();
                    \Log::info('Respuesta ipapi.co', $data);
                    $location = trim(
                        ($data['city'] ?? '') . ', ' .
                        ($data['region'] ?? '') . ', ' .
                        ($data['country_name'] ?? ''),
                        ', '
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Error obteniendo ubicación: ' . $e->getMessage());
                $location = null;
            }
        }

        $actividad = \App\Models\LoginActivity::create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
            'login_at' => now(),
            'location' => $location,
            'token' => $token,  // Aquí guardamos el token
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'session_id' => $actividad->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }




    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            return response()->json($user);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token no válido o expirado'], 401);
        }
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Sesión cerrada correctamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo cerrar sesión'], 500);
        }
    }
    
}
