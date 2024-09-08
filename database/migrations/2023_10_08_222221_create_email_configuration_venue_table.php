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
        Schema::create('email_configuration_venue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_configuration_id');
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

            $table->foreign('email_configuration_id')->references('id')->on('email_configurations');
            $table->foreign('venue_id')->references('id')->on('restaurants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_configuration_venue');
    }
};
