<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {

        Schema::dropIfExists('resource_allocations');
        Schema::create('resource_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->morphs('assignable');  // For any entity that needs resource allocation
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->decimal('quantity', 12, 2);
            $table->timestamp('allocated_at');
            $table->timestamp('return_at')->nullable();
            $table->string('status');
            $table->foreignId('allocated_by')->constrained('employees');
            $table->timestamps();
            $table->softDeletes();
        });


    }

    public function down()
    {

        Schema::dropIfExists('resource_allocations');
    }
};
