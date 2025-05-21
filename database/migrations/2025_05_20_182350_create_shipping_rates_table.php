<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/xxxx_xx_xx_create_shipping_rates_table.php
public function up()
{
    Schema::create('shipping_rates', function (Blueprint $table) {
        $table->id();
        $table->string('cp_origen', 10);
        $table->string('cp_destino_inicio', 10);
        $table->string('cp_destino_fin', 10);
        $table->decimal('costo_envio', 8, 2);
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
