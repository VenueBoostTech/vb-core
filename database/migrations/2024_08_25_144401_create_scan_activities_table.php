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
        Schema::create('scan_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->timestamp('scan_time');
            $table->enum('scan_type', ['add_new_product', 'update_product_inventory', 'warehouse_transfer']);
            $table->foreignId('moved_to_warehouse')->nullable()->constrained('inventory_warehouses')->onDelete('set null');
            $table->foreignId('moved_from_warehouse')->nullable()->constrained('inventory_warehouses')->onDelete('set null');
            $table->unsignedBigInteger('venue_id');
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');

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
        Schema::dropIfExists('scan_activities');
    }
};
