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
        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('discount_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('discount_value', 8, 2); // Assuming a precision of 2 for currency
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('cascade'); // Cascade on delete ensures if an order is deleted, its related discount link also gets deleted

            $table->foreign('discount_id')
                ->references('id')->on('discounts') // Assuming your discount table name is 'discounts'
                ->onDelete('cascade');

            $table->foreign('customer_id')
                ->references('id')->on('customers') // Assuming you have a 'customers' table
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_discounts');
    }
};
