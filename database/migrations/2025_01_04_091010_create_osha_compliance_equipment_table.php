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
        Schema::create('osha_compliance_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('construction_site_id')->constrained('construction_site');
            $table->string('title');
            $table->string('sub_title')->nullable();
            $table->string('last_inspection_date')->nullable();
            $table->string('next_inspection_date')->nullable();
            $table->enum('status', ['compliant', 'non_compliant', 'pending'])->default('pending');
            $table->json('requirements')->nullable();
            $table->json('required_actions')->nullable();
            $table->foreignId('assigned_to')->constrained('employees')->nullable();
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
        Schema::dropIfExists('osha_compliance_equipment');
    }
};
