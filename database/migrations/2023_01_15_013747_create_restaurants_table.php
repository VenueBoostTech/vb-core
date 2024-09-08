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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('address');
            $table->string('phone_number');
            $table->string('email');
            $table->string('website');
            $table->string('cuisine_type');
            $table->string('open_hours');
            $table->string('pricing');
            $table->integer('capacity');
            $table->string('logo');
            $table->string('cover');
            $table->text('amenities');
            $table->string('short_code')->unique();
            $table->string('app_key')->unique()->nullable();
            $table->boolean('is_main_venue')->default(0);
            $table->boolean('accept_waitlist_email')->default(1);
            $table->boolean('accept_reservation_status_email')->default(1);
            $table->boolean('accept_promotion_email')->default(1);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('restaurants');
    }
};
