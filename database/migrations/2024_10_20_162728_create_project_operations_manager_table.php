<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('project_operations_manager', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained('app_projects')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->primary(['project_id', 'employee_id']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_operations_manager');
    }
};
