<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->boolean('was_ordered')->default(false);
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);

            // Assuming you have a customers table. If not, adjust accordingly.
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wishlist_items');
    }
};
