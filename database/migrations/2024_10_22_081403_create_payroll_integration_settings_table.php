<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payroll_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->string('provider'); // adp, workday, gusto, paychex, quickbooks
            $table->string('environment')->default('sandbox');
            $table->json('credentials');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('venue_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');

            // Ensure only one active integration per venue
            $table->unique(['venue_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_integration_settings');
    }
};
