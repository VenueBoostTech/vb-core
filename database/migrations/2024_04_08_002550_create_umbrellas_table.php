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
        Schema::create('umbrellas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->string('uuid', 10);
            $table->unsignedBigInteger('area_id');
            $table->integer('nr_of_seats');
            $table->boolean('status')->default(true);
            $table->decimal('price_rate');
            $table->enum('price_rate_type', ['per_umbrella', 'per_seat']);
            $table->text('description')->nullable();
            $table->string('details_url')->nullable();
            $table->text('condition');
            $table->time('check_in')->nullable();
            $table->string('photo_url')->nullable();
            $table->integer('number');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('area_id')->references('id')->on('venue_beach_areas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('umbrellas');
    }
};
