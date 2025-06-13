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
            // Añade la nueva columna 'status' con un valor por defecto 'active'
            $table->string('status')->default('active')->after('stock'); // Puedes elegir la posición
            // Si ya tienes productos existentes, puedes establecer su estado a 'active' aquí:
            // \DB::table('products')->update(['status' => 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Elimina la columna 'status' si se revierte la migración
            $table->dropColumn('status');
        });
    }
};
