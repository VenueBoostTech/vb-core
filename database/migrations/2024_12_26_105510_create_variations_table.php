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
        Schema::create('variations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('value_id');
            $table->unsignedBigInteger('venue_id');
            $table->decimal('price', 8, 2);

            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->foreign('attribute_id')
                ->references('id')
                ->on('product_attributes')
                ->onDelete('cascade');

            $table->foreign('value_id')
                ->references('id')
                ->on('attribute_values')
                ->onDelete('cascade');

            $table->foreign('venue_id')
                ->references('id')
                ->on('restaurants')
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
        Schema::dropIfExists('variations');
    }
};
