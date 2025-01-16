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
        Schema::create('quality_inspections_construction', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_project_id');
            $table->unsignedBigInteger('inspector_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('location');
            $table->enum('inspection_type', ['structural', 'materials', 'finishing', 'safety'])->nullable();
            $table->string('signature')->nullable();
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
        Schema::dropIfExists('quality_inspections_construction');
    }
};
