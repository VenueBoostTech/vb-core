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
        Schema::create('faq_cats', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faq_cat_id');
            $table->string('question');
            $table->text('answer');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('faq_cat_id')->references('id')->on('faq_cats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('faq_cats');
    }
};
