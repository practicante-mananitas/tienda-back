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
        Schema::create('envios_manual', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();      // 👈 nuevo
            $table->unsignedBigInteger('address_id')->nullable();
            $table->decimal('peso', 8, 2)->nullable();
            $table->decimal('alto', 8, 2)->nullable();
            $table->decimal('ancho', 8, 2)->nullable();
            $table->decimal('largo', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envios_manual');
    }
};
