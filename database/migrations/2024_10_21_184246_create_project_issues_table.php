<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectIssuesTable extends Migration
{
    public function up()
    {
        Schema::create('project_issues', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('app_project_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('name')->nullable();
            $table->text('issue');
            $table->enum('status', ['open', 'in-progress', 'resolved'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('app_project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_issues');
    }
}
