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
Schema::create('pedidos', function (Blueprint $table) {
    $table->id();
    $table->string('external_reference')->nullable();
    $table->string('payment_id')->nullable();
    $table->string('status')->nullable(); // approved, pending, etc.
    $table->unsignedBigInteger('user_id')->nullable(); // si tienes login
    $table->unsignedBigInteger('address_id')->nullable();
    $table->decimal('total', 10, 2);
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
