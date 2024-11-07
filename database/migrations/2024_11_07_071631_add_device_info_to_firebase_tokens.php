<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('firebase_user_tokens', function (Blueprint $table) {
            $table->string('device_id')->after('firebase_token')->nullable();
            $table->enum('device_type', ['ios', 'android'])->after('device_id')->nullable();
            $table->string('device_model')->after('device_type')->nullable();
            $table->string('os_version')->after('device_model')->nullable();
            $table->string('app_version')->after('os_version')->nullable();
            $table->boolean('is_active')->after('app_version')->default(true);
            $table->timestamp('last_used_at')->after('is_active')->nullable();

            // Indexes
            $table->index(['user_id', 'device_id']);
            $table->index(['is_active']);
        });
    }

    public function down()
    {
        Schema::table('firebase_user_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'device_id',
                'device_type',
                'device_model',
                'os_version',
                'app_version',
                'is_active',
                'last_used_at'
            ]);

            $table->dropIndex(['user_id', 'device_id']);
            $table->dropIndex(['is_active']);
        });
    }
};
