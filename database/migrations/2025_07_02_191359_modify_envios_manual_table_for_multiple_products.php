<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Asegurar que no hay NULL en product_id antes de modificar la columna
        DB::table('envios_manual')
            ->whereNull('product_id')
            ->update(['product_id' => 0]); // O algún ID válido, o eliminar esos registros si no aplican

        Schema::table('envios_manual', function (Blueprint $table) {
            // Quitar esta línea porque ya existe la columna
            // $table->uuid('pedido_uid')->nullable()->after('id');

            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }

    public function down()
    {
        Schema::table('envios_manual', function (Blueprint $table) {
            // No eliminar la columna porque ya existe y no se agrega aquí
            // $table->dropColumn('pedido_uid');

            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }
};
