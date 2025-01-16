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
        Schema::create('equipment_check_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_check_in_check_out_id');
            $table->string('photo');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('equipment_check_in_check_out_id')->references('id')->on('equipment_check_in_check_out_processes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipment_check_photos');
    }
};
