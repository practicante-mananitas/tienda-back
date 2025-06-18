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
    Schema::create('revoked_tokens', function (Blueprint $table) {
        $table->id();
        $table->string('token')->unique();
        $table->timestamp('revoked_at')->useCurrent();
    });
}

public function down()
{
    Schema::dropIfExists('revoked_tokens');
}

};
