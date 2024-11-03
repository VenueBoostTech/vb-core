<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // drop table if exist
        Schema::create('app_project_timesheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_project_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('venue_id');
            $table->timestamp('clock_in_time');
            $table->timestamp('clock_out_time')->nullable();
            $table->text('work_description')->nullable();
            $table->json('location_data')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('app_project_id')->references('id')->on('app_projects')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_project_timesheets');
    }
};
