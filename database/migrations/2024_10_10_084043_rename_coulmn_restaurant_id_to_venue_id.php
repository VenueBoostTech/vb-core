<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('login_activities', function (Blueprint $table) {
            //
            $table->renameColumn('restaurant_id', 'venue_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('login_activities', function (Blueprint $table) {
            //
            $table->renameColumn('venue_id', 'restaurant_id');
        });
    }
};
