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
        Schema::create('vb_store_attributes_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('vb_store_attributes');
            $table->string('option_name');
            $table->string('option_url');
            $table->text('option_description')->nullable();
            $table->string('option_photo')->nullable();
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
        Schema::dropIfExists('vb_store_attributes_options');
    }
};
