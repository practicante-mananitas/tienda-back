<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('calle');
            $table->string('numero_interior')->nullable();
            $table->string('codigo_postal');
            $table->string('estado');
            $table->string('municipio');
            $table->string('localidad');
            $table->string('colonia');
            $table->enum('tipo_domicilio', ['residencial', 'laboral']);
            $table->text('indicaciones_entrega')->nullable();
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
