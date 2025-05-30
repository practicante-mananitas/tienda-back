<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SkydropxService
{
    public function cotizarEnvio(string $cpDestino, array $paquetes)
    {
        $response = Http::withHeaders([
            'Authorization' => env('SKYDROPX_TOKEN'),
            'Content-Type' => 'application/json'
        ])->post('https://app.skydropx.com/api/v1/quotations', [
            'address_from' => ['postal_code' => '62586'], // <-- cambia esto por tu CP de origen real
            'address_to' => ['postal_code' => $cpDestino],
            'parcels' => $paquetes
        ]);

        return $response->json();
    }

      public function getAccessToken()
    {
        if (Cache::has('skydropx_token')) {
            return Cache::get('skydropx_token');
        }

        $response = Http::post(config('services.skydropx.token_url'), [
            'client_id' => config('services.skydropx.client_id'),
            'client_secret' => config('services.skydropx.client_secret'),
            'grant_type' => 'client_credentials'
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $token = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 7200;

            Cache::put('skydropx_token', $token, now()->addSeconds($expiresIn - 60));

            return $token;
        }

        throw new \Exception('No se pudo obtener el token de Skydropx');
    }
}

        
