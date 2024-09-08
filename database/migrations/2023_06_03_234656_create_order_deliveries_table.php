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
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('delivery_provider_id')->constrained('delivery_providers');
            $table->foreignId('restaurant_id')->constrained('restaurants');
            $table->string('delivery_status');
            $table->string('external_tracking_url')->nullable();
            $table->string('doordash_order_id')->nullable();
            $table->string('external_delivery_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_deliveries');
    }
};
