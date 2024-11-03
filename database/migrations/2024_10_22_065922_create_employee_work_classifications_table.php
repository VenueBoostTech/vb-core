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
        Schema::create('employee_work_classifications', function (Blueprint $table) {

        $table->id();
        $table->unsignedBigInteger('employee_id');
        $table->unsignedBigInteger('venue_id');
        $table->string('classification_type'); // exempt, non-exempt
        $table->decimal('standard_rate', 10, 2);
        $table->decimal('overtime_rate', 10, 2);
        $table->integer('standard_hours_per_week')->default(40);
        $table->json('break_requirements');
        $table->timestamps();

        $table->foreign('employee_id')
            ->references('id')
            ->on('employees')
            ->onDelete('cascade');
        $table->foreign('venue_id')
            ->references('id')
            ->on('restaurants')
            ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_work_classifications');
    }
};
