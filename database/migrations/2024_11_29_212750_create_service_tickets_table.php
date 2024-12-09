<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('client_id')->constrained('app_clients');
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests');
            $table->foreignId('app_project_id')->nullable()->constrained('app_projects');
            $table->foreignId('assigned_to')->nullable()->constrained('employees');
            $table->string('status');
            $table->text('service_description');
            $table->text('work_performed')->nullable();
            $table->json('materials_used')->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Client sign-off fields
            $table->text('client_notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->string('signed_by_title')->nullable();
            $table->boolean('client_satisfied')->nullable();

            // Additional metadata
            $table->json('completion_checklist')->nullable();
            $table->decimal('service_duration', 8, 2)->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_tickets');
    }
};
