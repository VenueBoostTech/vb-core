<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        //drop table if exists
        Schema::dropIfExists('inventory_reports');
        Schema::create('inventory_reports', function (Blueprint $table) {
            $table->id();
            $table->string('period');
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('creator_user_id');
            $table->text('pdf_data')->nullable();
            $table->string('pdf_url')->nullable();
            $table->timestamps();

            $table->foreign('restaurant_id')
                ->references('id')
                ->on('restaurants')
                ->onDelete('cascade');

            $table->foreign('creator_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('inventory_reports');
    }
};
