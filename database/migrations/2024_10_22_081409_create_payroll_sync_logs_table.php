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
        Schema::create('payroll_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->string('provider');
            $table->string('sync_type'); // employee, timesheet, attendance, payout
            $table->json('payload');
            $table->json('response')->nullable();
            $table->string('status'); // success, failed
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('venue_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');

            // Add indexes for common queries
            $table->index(['venue_id', 'status']);
            $table->index(['venue_id', 'sync_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_sync_logs');
    }
};
