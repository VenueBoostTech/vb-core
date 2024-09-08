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
        Schema::create('occupancy_rate_forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_evaluation_id');
            $table->foreign('model_evaluation_id')->references('id')->on('model_evaluation');
            $table->date('date');
            $table->float('occupancy_rate');
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
        Schema::dropIfExists('occupancy_rate_forecasts');
    }
};
