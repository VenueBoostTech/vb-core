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
        Schema::create('venue_surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->string('surveymonkey_id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->integer('response_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venue_surveys');
    }
};
