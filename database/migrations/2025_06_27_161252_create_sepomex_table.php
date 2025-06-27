<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSepomexTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sepomex', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('idEstado');
            $table->string('estado', 35);
            $table->unsignedSmallInteger('idMunicipio');
            $table->string('municipio', 60);
            $table->string('ciudad', 60)->nullable();
            $table->string('zona', 15);
            $table->mediumInteger('cp')->unsigned();
            $table->string('asentamiento', 70);
            $table->string('tipo', 20);
            $table->timestamps(); // opcional, puedes quitar si no usas created_at/updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sepomex');
    }
}
