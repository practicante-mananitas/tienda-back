<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

// database/seeders/ShippingRatesSeeder.php
// use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingRatesSeeder extends Seeder
{
      public function run()
    {
        DB::table('shipping_rates')->insert([
            // CDMX
            ['cp_origen' => '62586', 'cp_destino_inicio' => '01000', 'cp_destino_fin' => '16999', 'costo_envio' => 200.00],

            // Estado de México
            ['cp_origen' => '62586', 'cp_destino_inicio' => '50000', 'cp_destino_fin' => '57999', 'costo_envio' => 200.00],

            // Puebla
            ['cp_origen' => '62586', 'cp_destino_inicio' => '72000', 'cp_destino_fin' => '75999', 'costo_envio' => 200.00],

            // Veracruz
            ['cp_origen' => '62586', 'cp_destino_inicio' => '91000', 'cp_destino_fin' => '93999', 'costo_envio' => 200.00],

            // Jalisco
            ['cp_origen' => '62586', 'cp_destino_inicio' => '44000', 'cp_destino_fin' => '49999', 'costo_envio' => 200.00],

            // Nuevo León
            ['cp_origen' => '62586', 'cp_destino_inicio' => '64000', 'cp_destino_fin' => '67999', 'costo_envio' => 220.00],

            // Baja California
            ['cp_origen' => '62586', 'cp_destino_inicio' => '21000', 'cp_destino_fin' => '22999', 'costo_envio' => 280.00],

            // Chiapas
            ['cp_origen' => '62586', 'cp_destino_inicio' => '29000', 'cp_destino_fin' => '30999', 'costo_envio' => 240.00],

            // Quintana Roo
            ['cp_origen' => '62586', 'cp_destino_inicio' => '77000', 'cp_destino_fin' => '77999', 'costo_envio' => 260.00],

            // Yucatán
            ['cp_origen' => '62586', 'cp_destino_inicio' => '97000', 'cp_destino_fin' => '97999', 'costo_envio' => 250.00],

            // Sinaloa
            ['cp_origen' => '62586', 'cp_destino_inicio' => '80000', 'cp_destino_fin' => '82999', 'costo_envio' => 230.00],

            // Oaxaca
            ['cp_origen' => '62586', 'cp_destino_inicio' => '68000', 'cp_destino_fin' => '71999', 'costo_envio' => 230.00],

            // Morelos (zona local)
            ['cp_origen' => '62586', 'cp_destino_inicio' => '62000', 'cp_destino_fin' => '62999', 'costo_envio' => 200.00],

            // Fallback nacional
            ['cp_origen' => '62586', 'cp_destino_inicio' => '00000', 'cp_destino_fin' => '99999', 'costo_envio' => 290.00],
        ]);
    }
}
