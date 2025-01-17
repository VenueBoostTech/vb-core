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
        Schema::create('construction_site_safety_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('construction_site_id')->constrained('construction_site');
            $table->string('title');
            $table->foreignId('assigned_to')->constrained('employees');
            $table->date('due_date');
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
        Schema::dropIfExists('construction_site_safety_checklists');
    }
};
