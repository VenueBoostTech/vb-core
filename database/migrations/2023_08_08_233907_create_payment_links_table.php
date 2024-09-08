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
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->enum('payment_type', ['card', 'cash']);
            $table->enum('payment_structure', ['total', 'per_person']);
            $table->integer('party_size')->nullable();
            $table->decimal('total', 10, 2);
            $table->decimal('price_per_person', 10, 2)->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('link')->nullable();
            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

            // Add foreign key constraint for venue_id
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_links');
    }
};
