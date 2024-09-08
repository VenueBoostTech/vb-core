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
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('name')->nullable(false);
            $table->text('description')->nullable();
            $table->datetime('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'cancelled', 'completed']);
            $table->timestamps();

            $table->foreign('rental_unit_id')->references('id')->on('rental_units')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('housekeeping_tasks');
    }
};
