<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockCalculationsTable extends Migration
{
    public function up()
    {
        Schema::create('stock_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->date('sync_date')->nullable();
            $table->string('calculation_type'); // 'variants' or 'single_products'
            $table->string('status')->default('pending'); // New column for status
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_calculations');
    }
}
