<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\RevokedToken;

class CheckRevokedToken
{
    public function handle($request, Closure $next)
    {
        $token = JWTAuth::getToken();

        if ($token && RevokedToken::where('token', $token)->exists()) {
            return response()->json(['error' => 'Token inválido, sesión cerrada.'], 401);
        }

        return $next($request);
    }
}
