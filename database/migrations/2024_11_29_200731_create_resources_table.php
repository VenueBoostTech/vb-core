<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');  // material, equipment, tool, etc.
            $table->string('unit');  // pieces, hours, kg, etc.
            $table->decimal('quantity_available', 12, 2)->default(0);
            $table->decimal('minimum_quantity', 12, 2)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->json('specifications')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });



    }

    public function down()
    {
        Schema::dropIfExists('resources');
    }
};
