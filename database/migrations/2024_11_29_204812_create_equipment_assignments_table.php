<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
    Schema::dropIfExists('equipment_assignments');
        Schema::create('equipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->onDelete('cascade');
            $table->morphs('assignable'); // Can be assigned to site, project, etc.
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('assigned_to')->constrained('employees');
            $table->timestamp('assigned_at');
            $table->timestamp('return_expected_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_assignments');
    }
};
