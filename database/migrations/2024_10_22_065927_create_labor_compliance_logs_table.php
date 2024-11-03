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
        Schema::create('labor_compliance_logs', function (Blueprint $table) {
            $table->id();
        $table->unsignedBigInteger('employee_id');
        $table->unsignedBigInteger('venue_id');
        $table->unsignedBigInteger('timesheet_id')->nullable();
        $table->string('event_type');
        $table->string('severity'); // warning, violation
        $table->text('description');
        $table->json('details')->nullable();
        $table->timestamps();

        $table->foreign('employee_id')
            ->references('id')
            ->on('employees')
            ->onDelete('cascade');
        $table->foreign('venue_id')
            ->references('id')
            ->on('restaurants')
            ->onDelete('cascade');
        $table->foreign('timesheet_id')
            ->references('id')
            ->on('app_project_timesheets')
            ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('labor_compliance_logs');
    }
};
