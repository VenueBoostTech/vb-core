<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('app_projects', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('description');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('status')->default('pending')->after('end_date');
            $table->foreignId('department_id')->nullable()->after('status')->constrained()->onDelete('set null');
        });

        Schema::create('app_project_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_project_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::table('app_projects', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['start_date', 'end_date', 'status', 'department_id']);
        });

        Schema::dropIfExists('app_project_team');
    }
};
