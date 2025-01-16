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
        Schema::create('safety_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('osha_compliance_id')->constrained('osha_compliance_equipment');
            $table->foreignId('construction_site_id')->constrained('construction_site');
            $table->string('ppe_compliance')->nullable();
            $table->string('fall_protection')->nullable();
            $table->string('key_findings')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'in_progress'])->default('scheduled');
            $table->timestamp('audited_at')->useCurrent();
            $table->foreignId('audited_by')->constrained('employees')->nullable();
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
        Schema::dropIfExists('safety_audit');
    }
};
