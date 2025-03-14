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
        if (Schema::hasTable('inventory_syncs')) {
            Schema::table('inventory_syncs', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_syncs', 'batch_id')) {
                    $table->string('batch_id')->nullable()->index();
                }

                if (!Schema::hasColumn('inventory_syncs', 'type')) {
                    $table->string('type')->default('product');
                }

                if (!Schema::hasColumn('inventory_syncs', 'status')) {
                    $table->string('status')->default('pending');
                }

                if (!Schema::hasColumn('inventory_syncs', 'total_pages')) {
                    $table->integer('total_pages')->default(0);
                }

                if (!Schema::hasColumn('inventory_syncs', 'processed_pages')) {
                    $table->integer('processed_pages')->default(0);
                }

                if (!Schema::hasColumn('inventory_syncs', 'started_at')) {
                    $table->timestamp('started_at')->nullable();
                }

                if (!Schema::hasColumn('inventory_syncs', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
            });
        } else {
            // Create the table if it doesn't exist
            Schema::create('inventory_syncs', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id')->nullable()->index();
                $table->string('type')->default('product');
                $table->string('status')->default('pending');
                $table->integer('total_pages')->default(0);
                $table->integer('processed_pages')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // Check if the pivot table exists, if not create it
        // IMPORTANT: Using inventory_sync_venue without 's' to match the model
        if (!Schema::hasTable('inventory_sync_venue')) {
            Schema::create('inventory_sync_venue', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_sync_id');
                $table->unsignedBigInteger('venue_id');
                $table->timestamp('last_sync_at')->nullable(); // Added to match your model
                $table->string('status')->nullable(); // Added to match your model
                $table->timestamps();

                $table->foreign('inventory_sync_id')->references('id')->on('inventory_syncs')->onDelete('cascade');
                // Changed from 'venues' to 'restaurants' to match Restaurant model
                $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');

                $table->unique(['inventory_sync_id', 'venue_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
