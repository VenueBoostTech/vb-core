<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQualityInspectionsTable extends Migration
{
    public function up()
    {
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('app_project_id');
            $table->unsignedBigInteger('team_leader_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('name')->nullable();
            $table->text('remarks');
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->date('inspection_date');
            $table->integer('rating')->nullable();
            $table->text('improvement_suggestions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('app_project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('team_leader_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quality_inspections');
    }
}
