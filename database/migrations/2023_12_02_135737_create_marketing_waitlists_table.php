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
        Schema::create('marketing_waitlists', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->enum('waitlist_type', ['pre_launch', 'launch'])->default('pre_launch');
            $table->string('venue_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('country_code')->nullable();
            $table->string('full_name')->nullable();
            $table->boolean('converted_to_venue')->default(false);
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
        Schema::dropIfExists('marketing_waitlists');
    }
};
