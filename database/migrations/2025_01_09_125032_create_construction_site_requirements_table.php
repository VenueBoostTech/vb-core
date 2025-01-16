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
        Schema::create('construction_site_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('construction_site_id')->constrained('construction_site');
            $table->string('title');
            $table->string('description');
            $table->enum('type', ['site_specific', 'general'])->default('general');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'complaint', 'action_required'])->default('pending');
            $table->foreignId('assigned_to')->constrained('employees');
            $table->date('last_check_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('construction_site_requirements');
    }
};
