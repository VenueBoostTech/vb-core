<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('app_project_id');
            $table->unsignedBigInteger('operation_manager_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('name')->nullable();
            $table->string('description');
            $table->enum('status', ['pending', 'in-progress', 'completed'])->default('pending');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('app_project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('operation_manager_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('work_orders');
    }
}
