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
        Schema::table('marketing_links', function (Blueprint $table) {
            $table->unsignedBigInteger('venue_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketing_links', function (Blueprint $table) {
            $table->unsignedBigInteger('venue_id')->nullable(false)->change();
        });
    }
};
