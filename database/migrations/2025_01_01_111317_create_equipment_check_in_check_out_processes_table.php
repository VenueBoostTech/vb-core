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
        Schema::create('equipment_check_in_check_out_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('employee_id')->constrained('employees');
            $table->enum('type', ['check_in', 'check_out']);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipment_check_in_check_out_processes');
    }
};
