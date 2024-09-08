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
        Schema::create('vt_devices', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['camera', 'other'])->default('camera');
            $table->string('device_id');
            $table->string('device_nickname')->nullable();
            $table->string('location');
            $table->enum('brand', ['UNV', 'Hikvision', 'Other']);
            $table->enum('setup_status', ['configured', 'active', 'inactive', 'not configured']);
            $table->unsignedBigInteger('venue_id');
            $table->foreign('venue_id')->references('id')->on('restaurants');
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
        Schema::dropIfExists('vt_devices');
    }
};
