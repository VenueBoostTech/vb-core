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
        Schema::create('hygiene_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->dateTime('inspection_date');
            $table->string('inspector_name');
            $table->text('observations');
            // remind me before log date hours
            $table->integer('remind_me_before_log_date_hours')->nullable();
            // inspection result status enum
            $table->enum('inspection_result_status', ['pass', 'fail', 'pending'])->default('pending');
            $table->dateTime('next_inspection_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hygiene_inspections');
    }
};
