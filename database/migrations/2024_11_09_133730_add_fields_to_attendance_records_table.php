<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Add duration column to track work hours
            $table->integer('duration_minutes')->nullable()->after('is_within_geofence');

            // Add status column for better tracking
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->after('is_within_geofence');

            // Add notes/comments field
            $table->text('notes')->nullable()->after('status');

            // Add approved_by field
            $table->foreignId('approved_by')
                ->nullable()
                ->after('notes')
                ->constrained('employees')
                ->nullOnDelete();

            // Add indices for better query performance
            $table->index('scan_type');
            $table->index('scanned_at');
            $table->index(['employee_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'duration_minutes',
                'status',
                'notes',
                'approved_by'
            ]);

            $table->dropIndex(['scan_type']);
            $table->dropIndex(['scanned_at']);
            $table->dropIndex(['employee_id', 'scanned_at']);
        });
    }
};
