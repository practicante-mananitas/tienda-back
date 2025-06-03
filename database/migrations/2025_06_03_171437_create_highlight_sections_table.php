<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::create('highlight_sections', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique(); // Ej: top-vendidos, recomendados, ofertas
                $table->string('titulo');         // Ej: Top Vendidos del Día
                $table->string('icono');          // Ej: 🔥
                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('highlight_sections');
    }
};
