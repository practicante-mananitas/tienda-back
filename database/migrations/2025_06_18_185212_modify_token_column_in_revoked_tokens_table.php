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
    // Cambia el tipo de la columna token a TEXT
    Schema::table('revoked_tokens', function (Blueprint $table) {
        $table->text('token')->change();
    });
}


    /**
     * Reverse the migrations.
     */
public function down()
{
    Schema::table('revoked_tokens', function (Blueprint $table) {
        $table->string('token', 255)->change();
    });
}

};
