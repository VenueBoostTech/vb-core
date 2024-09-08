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
        Schema::create('hygiene_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('status')->default('pending'); // e.g., pending, in progress, completed
            // health check name
            $table->string('item')->nullable();
            // assigned to email
            $table->string('assigned_to')->nullable();
            // remind hours before
            $table->integer('remind_hours_before')->nullable();
            // check date
            $table->date('check_date')->nullable();
            // type of check
            $table->string('type')->nullable();
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
        Schema::dropIfExists('hygiene_checks');
    }
};
