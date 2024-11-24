<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('client_id')->constrained('app_clients');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->foreignId('app_project_id')->nullable()->constrained('app_projects');
            $table->foreignId('service_id')->nullable()->constrained('services');
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_support_tickets');
    }
};
