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
        Schema::create('return_exchange_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_exchange_id')->constrained('returns_exchanges');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->enum('reason', ['defective', 'wrong_size', 'unwanted', 'other']);
            $table->enum('condition', ['new', 'used', 'damaged']);
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
        Schema::dropIfExists('return_exchange_items');
    }
};
