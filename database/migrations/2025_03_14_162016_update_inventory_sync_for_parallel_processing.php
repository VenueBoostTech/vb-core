<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateInventorySyncForParallelProcessing extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_syncs', function (Blueprint $table) {
            // If you really want to drop these columns, uncomment the lines below
            // $table->dropColumn('batch_id');
            // $table->dropColumn('type');
            // $table->dropColumn('status');
            // $table->dropColumn('total_pages');
            // $table->dropColumn('processed_pages');
            // $table->dropColumn('started_at');
            // $table->dropColumn('completed_at');
        });
    }
}
