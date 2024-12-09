<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('operation_delays');
        Schema::create('operation_delays', function (Blueprint $table) {
            $table->id();
            $table->morphs('impactable');  // For any entity that can be delayed
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->string('type');  // weather, technical, staffing, etc.
            $table->string('cause');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_minutes');
            $table->string('severity');
            $table->text('impact_description')->nullable();
            $table->json('affected_items')->nullable();
            $table->foreignId('reported_by')->constrained('employees');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('operation_delays');
    }
};
