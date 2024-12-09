<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('equipment_usage_logs');
        Schema::create('equipment_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->morphs('usageable'); // Can be linked to project, site, etc.
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->foreignId('operator')->constrained('employees');
            $table->decimal('fuel_consumed', 8, 2)->nullable();
            $table->json('performance_metrics')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_usage_logs');
    }
};
