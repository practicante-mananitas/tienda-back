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
use Illuminate\Support\Str;
use App\Models\RefreshToken;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/'
            ],
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ], [
            'password.regex' => 'La contraseña debe tener al menos una mayúscula, un número y un carácter especial.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'role' => 'user',
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Usuario registrado exitosamente. Revisa tu correo para verificar tu cuenta.',
            'user' => $user
        ]);
    }




    public function login(Request $request)
    {
        \Log::info('Login iniciado para email: ' . $request->email);
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        $user = JWTAuth::setToken($token)->toUser();

        // 🚫 Validar que el email esté verificado
        if (!$user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Debes verificar tu correo electrónico antes de iniciar sesión.'], 403);
        }

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
            'token' => $token,
        ]);

        $accessToken = JWTAuth::claims([
            'exp' => now()->addMinutes(60)->timestamp  // Access token válido 1 hora
        ])->fromUser($user);

        $refreshToken = Str::random(60); // Refresh token aleatorio

        // Guardar el refresh token en la base de datos
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(15) // Expira en 15 días
        ]);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => 3600,
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
            // Obtener usuario autenticado desde el token
            $user = JWTAuth::parseToken()->authenticate();

            // Invalidar el token JWT actual
            JWTAuth::invalidate(JWTAuth::getToken());

            // Eliminar todos los refresh tokens del usuario (opción 1)
            RefreshToken::where('user_id', $user->id)->delete();

            // --- Alternativa: eliminar sólo el refresh token recibido ---
            // $refreshToken = $request->input('refresh_token');
            // if ($refreshToken) {
            //     $hashed = hash('sha256', $refreshToken);
            //     RefreshToken::where('token', $hashed)->delete();
            // }

            return response()->json(['message' => 'Sesión cerrada correctamente y tokens eliminados']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo cerrar sesión'], 500);
        }
    }

    public function verifyEmail($id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            // Firma inválida, manda a página error en frontend
            return redirect(env('FRONTEND_URL') . '/#/email/verification-failed');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(env('FRONTEND_URL') . '/#/email/already-verified');
        }

        $user->markEmailAsVerified();

        return redirect(env('FRONTEND_URL') . '/#/email-verified');
    }
        
    public function refresh(Request $request)
    {
        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'Refresh token requerido'], 400);
        }

        $hashed = hash('sha256', $refreshToken);

        $record = \App\Models\RefreshToken::where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Refresh token inválido o expirado'], 401);
        }

        $user = User::find($record->user_id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $newAccessToken = JWTAuth::claims([
            'exp' => now()->addMinutes(60)->timestamp  // Otro 1 hora
        ])->fromUser($user);

        return response()->json([
            'access_token' => $newAccessToken,
            'token_type' => 'bearer',
            'expires_in' => 3600
        ]);
    }

}
