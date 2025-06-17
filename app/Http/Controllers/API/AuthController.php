<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
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
    $credentials = $request->only('email', 'password');

    if (!$token = JWTAuth::attempt($credentials)) {
        return response()->json(['message' => 'Credenciales incorrectas'], 401);
    }

    $user = JWTAuth::setToken($token)->toUser();

    return response()->json([
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => auth('api')->factory()->getTTL() * 60,
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

    
}
