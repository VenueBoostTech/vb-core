<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('app_projects', function (Blueprint $table) {
            $table->string('project_type')->nullable();
            $table->foreignId('address_id')->nullable()->constrained('addresses');
            $table->enum('project_category', ['inhouse', 'client'])->default('client');
            $table->enum('deal_status', ['won', 'lost', 'pending'])->default('pending');
            $table->foreignId('client_id')->nullable()->constrained('app_clients');
        });

        // Create project_team_leader pivot table
        Schema::create('project_team_leader', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained('app_projects')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->primary(['project_id', 'employee_id']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::table('app_projects', function (Blueprint $table) {
            $table->dropColumn(['project_type', 'project_category', 'deal_status']);
            $table->dropConstrainedForeignId('address_id');
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::dropIfExists('project_team_leader');
    }
};
