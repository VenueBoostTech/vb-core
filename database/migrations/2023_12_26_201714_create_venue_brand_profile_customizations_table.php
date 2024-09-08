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
        Schema::create('venue_brand_profile_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('element_id')->constrained('industry_brand_customization_elements');
            $table->enum('element_type', ['button', 'paragraph', 'h1', 'h2', 'h3'])->default('button');
            $table->string('customization_key');
            $table->text('customization_value');
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
        Schema::dropIfExists('venue_brand_profile_customizations');
    }
};
