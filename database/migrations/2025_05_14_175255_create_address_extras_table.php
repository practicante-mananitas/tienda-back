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
        Schema::create('address_extras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('address_id');
            
            $table->string('tipo_lugar');
            $table->string('barrio')->nullable();
            $table->string('nombre_casa')->nullable();
            $table->string('conserjeria')->nullable();
            $table->time('hora_apertura')->nullable();
            $table->time('hora_cierre')->nullable();
            $table->boolean('abierto24')->default(false);
            $table->json('dias')->nullable();

            $table->timestamps();

            $table->foreign('address_id')->references('id')->on('addresses')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('address_extras');
    }
};
