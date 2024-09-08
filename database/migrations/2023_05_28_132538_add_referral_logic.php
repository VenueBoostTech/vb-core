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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('referral_code')->nullable();
            $table->unsignedBigInteger('used_referral_id')->nullable();
        });

        Schema::create('restaurant_referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->nullable(false);
            $table->string('referral_code')->nullable(false);
            $table->tinyInteger('is_used')->nullable(false)->default(0);
            $table->unsignedInteger('register_id')->nullable();
            $table->timestamp('used_time')->nullable();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('referral_code');
            $table->dropColumn('used_referral_id');
        });

        Schema::dropIfExists('restaurant_referrals');
    }
};
