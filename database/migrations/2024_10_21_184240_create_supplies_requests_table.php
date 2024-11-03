<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuppliesRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('supplies_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('app_project_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('name')->nullable();
            $table->string('description');
            $table->enum('status', ['pending', 'approved', 'denied', 'provided'])->default('pending');
            $table->date('requested_date');
            $table->date('required_date');
            $table->text('admin_remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('app_project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplies_requests');
    }
}
