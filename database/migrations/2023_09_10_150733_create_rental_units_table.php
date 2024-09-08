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
        Schema::create('rental_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('about')->nullable();
            $table->text('about_space')->nullable();
            $table->text('about_guest_access')->nullable();
            $table->string('country')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->bigInteger('unit_code')->unique();
            $table->softDeletes();


            $table->unsignedBigInteger('venue_id');
            $table->timestamps();

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
        Schema::dropIfExists('rental_units');
    }
};
