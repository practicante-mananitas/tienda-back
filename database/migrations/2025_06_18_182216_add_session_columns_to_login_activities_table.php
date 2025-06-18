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
        Schema::table('login_activities', function (Blueprint $table) {
            $table->timestamp('logout_at')->nullable()->after('login_at');
            $table->timestamp('last_activity')->nullable()->after('logout_at');
        });
    }

    public function down()
    {
        Schema::table('login_activities', function (Blueprint $table) {
            $table->dropColumn(['logout_at', 'last_activity']);
        });
    }

};
