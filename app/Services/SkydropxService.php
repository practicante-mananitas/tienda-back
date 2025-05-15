<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
}

        
