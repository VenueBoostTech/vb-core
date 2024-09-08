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
        Schema::create('ecommerce_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('name');
            $table->string('url');
            $table->enum('type', ['shopify', 'woocommerce', 'magento', 'custom', 'other']);
            $table->softDeletes();
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
        Schema::dropIfExists('ecommerce_platforms');
    }
};
