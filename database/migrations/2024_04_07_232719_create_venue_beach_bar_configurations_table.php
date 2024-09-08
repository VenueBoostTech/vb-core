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
        Schema::create('venue_beach_bar_configurations', function (Blueprint $table) {
            $table->id();
            $table->boolean('has_restaurant_menu')->default(true);
            $table->boolean('has_beach_menu')->default(true);
            $table->time('default_umbrellas_check_in')->default('10:00');
            $table->string('currency')->default('USD');
            $table->unsignedBigInteger('venue_id');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venue_beach_bar_configurations');
    }
};
