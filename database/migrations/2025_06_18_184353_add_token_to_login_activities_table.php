<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTokenToLoginActivitiesTable extends Migration
{
    public function up()
    {
        Schema::table('login_activities', function (Blueprint $table) {
            $table->text('token')->nullable()->after('location');
        });
    }

    public function down()
    {
        Schema::table('login_activities', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
}
