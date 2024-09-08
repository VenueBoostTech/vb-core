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
        // drop the table if it exists
        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->string('name');
            $table->string('code');
            $table->foreignId('address_id')->constrained()->onDelete('cascade');
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
        Schema::dropIfExists('inventory_warehouses');
    }
};
