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
        Schema::create('inventory_alert_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_alert_id');
            $table->integer('stock_quantity_at_alert');
            $table->timestamp('alert_triggered_at');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('inventory_alert_id')->references('id')->on('inventory_alerts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_alert_history');
    }
};
