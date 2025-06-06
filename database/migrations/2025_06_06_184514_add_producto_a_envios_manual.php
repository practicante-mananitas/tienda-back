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
    Schema::table('envios_manual', function (Blueprint $table) {
        $table->unsignedBigInteger('product_id')->nullable();
        $table->integer('cantidad')->default(1);
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
