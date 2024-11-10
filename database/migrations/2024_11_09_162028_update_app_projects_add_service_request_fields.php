<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->constrained('app_clients');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('app_project_id')->nullable()->constrained('app_projects');
            $table->enum('status', [
                'Pending',
                'Scheduled',
                'In Progress',
                'Completed',
                'Cancelled'
            ])->default('Pending');
            $table->enum('priority', ['Low', 'Normal', 'High', 'Urgent'])->default('Normal');
            $table->timestamp('requested_date');
            $table->timestamp('preferred_date')->nullable();
            $table->timestamp('scheduled_date')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['venue_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index('app_project_id');
        });

        Schema::create('service_request_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type');
            $table->text('description');
            $table->foreignId('performed_by')->constrained('users');
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamps();

            // Index for better performance
            $table->index('service_request_id');
        });

        // Add service_request specific columns to app_projects table
        Schema::table('app_projects', function (Blueprint $table) {
            // Add after project_category
            $table->string('project_source')->after('project_category')
                ->default('manual')
                ->comment('manual, service_request');

            // Add service request specific fields if they don't exist
            if (!Schema::hasColumn('app_projects', 'service_id')) {
                $table->foreignId('service_id')->nullable()
                    ->after('project_source')
                    ->constrained('services');
            }

            // Add service-specific tracking fields
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->nullable();
            $table->json('service_details')->nullable()
                ->comment('Additional service-specific details');

            // Add indexes
            $table->index(['project_source', 'status']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        // Remove service request specific columns from app_projects
        Schema::table('app_projects', function (Blueprint $table) {
            $table->dropColumn([
                'project_source',
                'service_id',
                'quoted_price',
                'final_price',
                'service_details'
            ]);
        });

        Schema::dropIfExists('service_request_activities');
        Schema::dropIfExists('service_requests');
    }
};
