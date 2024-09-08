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
        Schema::create('fine_tuning_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->string('job_id');
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->unique(['venue_id', 'job_id'], 'venue_id_job_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fine_tuning_jobs');
    }
};
