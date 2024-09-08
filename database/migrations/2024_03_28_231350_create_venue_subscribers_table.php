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
        // drop the venue_subscribers table if it exists
        Schema::create('venue_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->unsignedBigInteger('venue_id');
            $table->boolean('subscribed')->default(true);
            $table->timestamps();

            // Define foreign key constraint
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
        Schema::dropIfExists('venue_subscribers');
    }
};
