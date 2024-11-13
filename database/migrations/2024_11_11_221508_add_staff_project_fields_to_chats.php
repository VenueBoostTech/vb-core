<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('chats', function (Blueprint $table) {
            // Make existing fields nullable
            $table->foreignId('end_user_id')->nullable()->change();
            $table->foreignId('venue_user_id')->nullable()->change();

            // Add new fields for internal chat
            $table->foreignId('sender_id')->nullable()->after('venue_user_id')
                ->constrained('users');
            $table->foreignId('receiver_id')->nullable()->after('sender_id')
                ->constrained('users');

            // Add project_id for staff chats
            $table->foreignId('project_id')->nullable()->after('venue_id')
                ->constrained('app_projects');
        });

        // First make the column nullable, modify enum, then make it NOT NULL
        DB::statement("ALTER TABLE chats MODIFY COLUMN type ENUM('order', 'booking', 'staff', 'client') NULL");

        // Update any NULL values to 'order' or whatever default you want
        DB::statement("UPDATE chats SET type = 'order' WHERE type IS NULL");

        // Now make it NOT NULL
        DB::statement("ALTER TABLE chats MODIFY COLUMN type ENUM('order', 'booking', 'staff', 'client') NOT NULL");
    }

    public function down()
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->foreignId('end_user_id')->nullable(false)->change();
            $table->foreignId('venue_user_id')->nullable(false)->change();

            // Drop columns and their foreign keys
            if (Schema::hasColumn('chats', 'sender_id')) {
                $table->dropForeign('chats_sender_id_foreign');
                $table->dropColumn('sender_id');
            }

            if (Schema::hasColumn('chats', 'receiver_id')) {
                $table->dropForeign('chats_receiver_id_foreign');
                $table->dropColumn('receiver_id');
            }

            if (Schema::hasColumn('chats', 'project_id')) {
                $table->dropForeign('chats_project_id_foreign');
                $table->dropColumn('project_id');
            }
        });
    }
};
