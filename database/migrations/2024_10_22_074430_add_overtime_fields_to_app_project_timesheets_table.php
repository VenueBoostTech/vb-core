<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_project_timesheets', function (Blueprint $table) {
            $table->decimal('regular_hours', 8, 2)->nullable()->after('total_hours');
            $table->decimal('overtime_hours', 8, 2)->nullable()->after('regular_hours');
            $table->decimal('double_time_hours', 8, 2)->nullable()->after('overtime_hours');
            $table->boolean('overtime_approved')->default(false)->after('double_time_hours');
            $table->unsignedBigInteger('overtime_approved_by')->nullable()->after('overtime_approved');
            $table->timestamp('overtime_approved_at')->nullable()->after('overtime_approved_by');

            // Add foreign key for overtime_approved_by
            $table->foreign('overtime_approved_by')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_project_timesheets', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['overtime_approved_by']);

            // Drop columns
            $table->dropColumn([
                'regular_hours',
                'overtime_hours',
                'double_time_hours',
                'overtime_approved',
                'overtime_approved_by',
                'overtime_approved_at'
            ]);
        });
    }
};
