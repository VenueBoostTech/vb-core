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
        Schema::create('report_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('construction_site_id')->constrained('construction_site');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->string('type_of_incident');
            $table->dateTime('date_time');
            $table->string('location');
            $table->string('description');
            $table->string('person_involved');
            $table->string('taken_action');
            $table->string('photos')->nullable();
            $table->string('withness_statement');
            $table->string('weather_condition');
            $table->string('lighting_condition');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->enum('status', ['pending', 'resolved', 'under_investigation', 'closed'])->default('pending');
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
        Schema::dropIfExists('report_incidents');
    }
};
