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
        Schema::create('employee_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_device_id');
            $table->text('token');
            $table->timestamps();

            $table->foreign('employee_device_id')->references('id')->on('employee_devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_device_tokens');
    }
};
