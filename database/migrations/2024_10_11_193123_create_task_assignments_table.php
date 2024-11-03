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
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');  // Links to tasks table
            $table->foreignId('employee_id')->constrained()->onDelete('cascade'); // Links to employees table
            $table->dateTime('assigned_at');  // When the employee was assigned to this task
            $table->dateTime('unassigned_at')->nullable();  // When the employee was unassigned
            $table->timestamps();

            $table->unique(['task_id', 'employee_id']); // Unique pair to avoid duplicates
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('task_assignments');
    }
};
