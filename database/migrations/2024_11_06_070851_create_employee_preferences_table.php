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

        // Table for employee preferences and permissions
        Schema::create('employee_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');

            // Communication preferences
            $table->boolean('email_notifications')->default(false);
            $table->boolean('sms_notifications')->default(true);

            // Tracking permissions
            $table->boolean('location_tracking_enabled')->default(false);
            $table->boolean('background_tracking_enabled')->default(false);
            $table->timestamp('tracking_enabled_at')->nullable();
            $table->timestamp('tracking_disabled_at')->nullable();

            // Audit timestamps
            $table->timestamps();

            // Ensure one preference record per employee
            $table->unique('employee_id');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_preferences');
    }
};
