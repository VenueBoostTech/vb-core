<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {

        Schema::create('gift_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_occasion_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('physical_store_id')->constrained('physical_stores')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['gift_occasion_id', 'product_id', 'physical_store_id'], 'gift_suggestion_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gift_suggestions');
    }
};
