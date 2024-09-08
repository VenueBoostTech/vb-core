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
        Schema::create('venue_contact_forms', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable(false); // required
            $table->string('phone')->nullable(); // optional
            $table->string('subject')->nullable(); // optional
            $table->string('email')->nullable(false); // required, not unique
            $table->text('content')->nullable(); // optional
            $table->unsignedBigInteger('venue_id');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
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
        Schema::dropIfExists('venue_contact_forms');
    }
};
