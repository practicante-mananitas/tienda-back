<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\LoginActivity;
use Tymon\JWTAuth\Facades\JWTAuth;

class ActualizarUltimaActividad
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $token = JWTAuth::getToken();

        if ($user && $token) {
            $ip = $request->ip();
            $agent = $request->userAgent();

            // Busca la sesión actual sin logout
            $session = LoginActivity::where('user_id', $user->id)
                ->where('ip_address', $ip)
                ->where('user_agent', $agent)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first();

            if ($session) {
                $session->last_activity = now();
                $session->save();
            }
        }

        return $next($request);
    }
}
