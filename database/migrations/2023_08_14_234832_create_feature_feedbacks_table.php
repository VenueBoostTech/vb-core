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
        Schema::create('feature_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->string('feature_name');
            $table->unsignedBigInteger('venue_id');
            $table->text('question_1');
            $table->text('question_2');
            $table->text('question_3');
            $table->boolean('question_1_answer');
            $table->boolean('question_2_answer');
            $table->boolean('question_3_answer');
            $table->text('additional_info_1')->nullable();
            $table->text('additional_info_2')->nullable();
            $table->text('additional_info_3')->nullable();
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('feature_feedbacks');
    }
};
