<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('site_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('construction_site_id')->constrained()->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->string('report_type'); // daily, inspection, incident, progress
            $table->date('report_date');
            $table->text('description');
            $table->json('weather_conditions')->nullable();
            $table->json('activities_performed')->nullable();
            $table->json('issues_identified')->nullable();
            $table->foreignId('reported_by')->constrained('employees');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('site_reports');
    }
};
