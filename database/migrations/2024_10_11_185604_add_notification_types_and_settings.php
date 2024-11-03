<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddNotificationTypesAndSettings extends Migration
{
    public function up()
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('notification_type_id')->constrained('notification_types')->after('vb_app_id');
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('notification_type_id')->constrained('notification_types')->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'notification_type_id']);
        });

        // Insert default notification types
        DB::table('notification_types')->insert([
            ['name' => 'check_in_out_reminders', 'description' => 'Check-in/out reminders'],
            ['name' => 'successful_check_ins_outs', 'description' => 'Successful check-ins/outs'],
            ['name' => 'important_announcements', 'description' => 'Important announcements/updates'],
            ['name' => 'task_notifications', 'description' => 'Tasks Notifications'],
            ['name' => 'shift_schedule_changes', 'description' => 'Shift & Schedule Changes'],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('notification_settings');
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['notification_type_id']);
            $table->dropColumn('notification_type_id');
        });
        Schema::dropIfExists('notification_types');
    }
}
