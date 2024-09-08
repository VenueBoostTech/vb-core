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
        Schema::create('affiliate_affiliate_program', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_id');
            $table->unsignedBigInteger('affiliate_program_id');
            $table->timestamps();

            $table->foreign('affiliate_id')->references('id')->on('affiliates')->onDelete('cascade');
            $table->foreign('affiliate_program_id')->references('id')->on('affiliate_programs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('affiliate_affiliate_program');
    }
};
