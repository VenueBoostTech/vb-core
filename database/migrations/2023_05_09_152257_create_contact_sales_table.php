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
        Schema::create('contact_sales', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable(false);
            $table->string('last_name')->nullable(false);
            $table->string('mobile')->nullable();
            $table->string('email')->nullable(false);
            $table->string('restaurant_name')->nullable(false);
            $table->string('restaurant_city')->nullable(false);
            $table->string('restaurant_state')->nullable(false);
            $table->string('restaurant_zipcode')->nullable(false);
            $table->string('restaurant_country')->nullable(false);
            $table->string('contact_reason')->nullable(false);
            $table->boolean('is_demo')->default(false);
            $table->string('status')->nullable(false)->default('pending'); // approved, declined
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
        Schema::dropIfExists('contact_sales');
    }
};
