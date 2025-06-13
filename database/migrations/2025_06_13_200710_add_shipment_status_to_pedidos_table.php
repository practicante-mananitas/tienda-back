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
            // Añade la nueva columna 'shipment_status'
            // Por defecto será 'in_process'.
            // Puedes añadirla después de la columna 'status' (estado de pago)
            $table->string('shipment_status')->default('in_process')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema->table('pedidos', function (Blueprint $table) {
            // Elimina la columna 'shipment_status' si se revierte la migración
            $table->dropColumn('shipment_status');
        });
    }
};
