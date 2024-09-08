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
        Schema::create('vision_track_configurations', function (Blueprint $table) {
            $table->id();
            // ai pipeline id
            $table->string('ai_pipeline_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('configuration_key');
            $table->string('configuration_value');

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
        Schema::dropIfExists('vision_track_configurations');
    }
};
