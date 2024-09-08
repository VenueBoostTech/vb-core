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
        Schema::create('wc_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('consumer_key')->unique();
            $table->string('consumer_secret');
            $table->string('consumer_wc_website')->unique();

            $table->unsignedBigInteger('venue_id');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wc_integrations');
    }
};
