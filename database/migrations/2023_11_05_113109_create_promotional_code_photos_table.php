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
        Schema::create('promotional_code_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotional_code_id');
            $table->string('image_path');

            $table->foreign('promotional_code_id')->references('id')->on('promotional_codes')->onDelete('cascade');
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
        Schema::dropIfExists('promotional_code_photos');
    }
};
