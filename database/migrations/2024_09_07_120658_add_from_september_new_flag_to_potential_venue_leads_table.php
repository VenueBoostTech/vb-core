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
        Schema::table('potential_venue_leads', function (Blueprint $table) {
            $table->boolean('from_september_new')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('potential_venue_leads', function (Blueprint $table) {
            $table->dropColumn('from_september_new');
        });
    }
};
