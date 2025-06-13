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
        Schema::table('products', function (Blueprint $table) {
            // Añade la columna 'stock' como un entero sin signo, con un valor por defecto de 0
            // y colócala después de la columna 'price'.
            $table->unsignedInteger('stock')->default(0)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Para revertir la migración, elimina la columna 'stock'.
            $table->dropColumn('stock');
        });
    }
};

