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
        Schema::table('pedidos', function (Blueprint $table) {
            // Añade la columna 'fecha_maxima_entrega' como una fecha, que puede ser nula inicialmente
            $table->date('fecha_maxima_entrega')->nullable()->after('status'); // Puedes ajustar 'after' según tu preferencia
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Para revertir la migración, elimina la columna
            $table->dropColumn('fecha_maxima_entrega');
        });
    }
};
