<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('type');

            // For polymorphic relations with other models
            $table->nullableMorphs('trackable');

            // For additional context about the activity
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['venue_id', 'created_at']);
            $table->index(['employee_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activities');
    }
};
