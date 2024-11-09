<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create leave types table
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Add leave management columns to schedules table
        Schema::table('schedules', function (Blueprint $table) {
            // Only add columns that don't already exist
            if (!Schema::hasColumn('schedules', 'date')) {
                $table->date('date');
            }
            if (!Schema::hasColumn('schedules', 'end_date')) {
                $table->date('end_date')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'start_time')) {
                $table->time('start_time');
            }
            if (!Schema::hasColumn('schedules', 'end_time')) {
                $table->time('end_time');
            }
            if (!Schema::hasColumn('schedules', 'status')) {
                $table->string('status')->default('active'); // active, time_off, cancelled
            }
            if (!Schema::hasColumn('schedules', 'total_days')) {
                $table->integer('total_days')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'reason')) {
                $table->text('reason')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'leave_type_id')) {
                $table->foreignId('leave_type_id')->nullable()
                    ->constrained('leave_types')
                    ->nullOnDelete();
            }
        });

        // Seed initial leave types
        DB::table('leave_types')->insert([
            [
                'name' => 'Vacation',
                'description' => 'Annual vacation leave',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Sick Leave',
                'description' => 'Medical related leave',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Personal',
                'description' => 'Personal time off',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Work From Home',
                'description' => 'Remote work arrangement',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Remove foreign key first
            $table->dropConstrainedForeignId('leave_type_id');

            // Remove the columns we added
            $table->dropColumn([
                'end_date',
                'total_days',
                'reason',
                'status'
            ]);
        });

        Schema::dropIfExists('leave_types');
    }
};
