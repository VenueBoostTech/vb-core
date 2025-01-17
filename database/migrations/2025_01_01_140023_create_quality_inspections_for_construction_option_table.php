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
        Schema::create('quality_inspections_construction_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qi_construction_id');
            $table->string('category');
            $table->string('name');
            $table->string('comment')->nullable();
            $table->enum('status', ['pass', 'fail','na'])->default('na');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('qi_construction_id', 'qi_construction_id')->references('id')->on('quality_inspections_construction')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('quality_inspections_construction_options');
    }
};
