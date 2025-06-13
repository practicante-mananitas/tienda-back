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
        Schema::table('pedido_items', function (Blueprint $table) {
            // Elimina la columna 'producto'
            if (Schema::hasColumn('pedido_items', 'producto')) { // Condicional para evitar error si ya no existe
                $table->dropColumn('producto');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            // Si necesitas revertir, puedes añadirla de nuevo, pero no es común revertir una eliminación de columna
            // $table->string('producto')->nullable(); // Podrías definirla como string o json si la quieres de vuelta
        });
    }
};

