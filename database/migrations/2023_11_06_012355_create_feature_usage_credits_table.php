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
        Schema::create('feature_usage_credits', function (Blueprint $table) {
            $table->id();
            $table->integer('balance')->default(0);
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

            // Add foreign key constraint for venue_id and restaurant_referral_id
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
        Schema::dropIfExists('feature_usage_credits');
    }
};
