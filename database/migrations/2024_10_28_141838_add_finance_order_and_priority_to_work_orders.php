<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFinanceOrderAndPriorityToWorkOrders extends Migration
{
    public function up()
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Just drop the foreign key constraint, keep the column
            $table->dropForeign(['app_project_id']);

            // Add the new foreign key reference
            $table->foreign('app_project_id')
                ->references('id')
                ->on('app_projects')
                ->onDelete('cascade');

            // Add new finance order id
            $table->string('finance_order_id')->nullable();

            // Add priority enum
            $table->enum('priority', ['low', 'medium', 'high'])
                ->default('medium');
        });
    }

    public function down()
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Remove new columns
            $table->dropColumn(['finance_order_id', 'priority']);

            // Drop the new foreign key reference
            $table->dropForeign(['app_project_id']);

            // Add back original foreign key
            $table->foreign('app_project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');
        });
    }
}
