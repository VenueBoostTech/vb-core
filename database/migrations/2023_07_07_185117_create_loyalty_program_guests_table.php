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
        Schema::create('loyalty_program_guests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loyalty_program_id');
            $table->unsignedInteger('guest_id');

            $table->foreign('loyalty_program_id')->references('id')->on('loyalty_programs')->onDelete('cascade');
            $table->foreign('guest_id')->references('id')->on('guests')->onDelete('cascade');
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
        Schema::dropIfExists('loyalty_program_guests');
    }
};
