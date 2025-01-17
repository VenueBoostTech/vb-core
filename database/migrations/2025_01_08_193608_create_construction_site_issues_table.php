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
        Schema::create('construction_site_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('construction_site_id')->constrained('construction_site');
            $table->foreignId('assigned_to')->nullable()->constrained('employees');
            $table->string('title');
            $table->string('location');
            $table->string('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->enum('status', ['open', 'in-progress', 'resolved'])->default('open');
            $table->string('image')->nullable();
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
        Schema::dropIfExists('construction_site_issues');
    }
};
