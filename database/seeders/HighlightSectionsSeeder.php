<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HighlightSection;

class HighlightSectionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

public function run()
{
    HighlightSection::insert([
        [
            'slug' => 'top-vendidos',
            'titulo' => 'Top Vendidos del Día',
            'icono' => '🔥',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'slug' => 'recomendados',
            'titulo' => 'Recomendados para ti',
            'icono' => '🎁',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'slug' => 'ofertas',
            'titulo' => 'Ofertas Relámpago',
            'icono' => '🏷️',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
}

}
