<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('schedule_type')->after('status')->nullable(); // shift, task, job
            $table->foreignId('project_id')->nullable()->after('schedule_type')->constrained('app_projects')->nullOnDelete();
            $table->string('priority')->nullable()->after('project_id'); // high, medium, low
            $table->dateTime('delivery_date')->nullable()->after('end_date');
        });
    }

    public function down()
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn(['schedule_type', 'project_id', 'priority', 'delivery_date']);
        });
    }
};
