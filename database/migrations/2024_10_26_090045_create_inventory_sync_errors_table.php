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
        Schema::create('inventory_sync_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('synchronization_id')->constrained('inventory_synchronizations')->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('error_type'); // API, Database, Validation etc.
            $table->text('error_message');
            $table->json('error_context')->nullable(); // For additional error details
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_sync_errors');
    }
};
