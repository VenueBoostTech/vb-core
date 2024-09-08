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
        Schema::create('shipping_zone_method_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_zone_id');
            $table->unsignedBigInteger('shipping_method_id');

            $table->boolean('has_minimum_order_amount')->default(false); // for free shipping
            $table->decimal('minimum_order_amount', 8, 2)->nullable(); // for flat rate
            $table->decimal('flat_rate_cost', 8, 2)->nullable(); // for flat rate
            $table->timestamps();

            $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones')->onDelete('cascade');
            $table->foreign('shipping_method_id')->references('id')->on('shipping_methods')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shipping_zone_method_pivot');
    }
};
