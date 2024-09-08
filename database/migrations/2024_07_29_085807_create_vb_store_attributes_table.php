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
        Schema::create('vb_store_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('vb_store_attributes_types');
            $table->string('attr_name');
            $table->string('attr_url');
            $table->text('attr_description')->nullable();
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
        Schema::dropIfExists('vb_store_attributes');
    }
};
