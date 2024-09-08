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
        Schema::create('venue_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->string('definition')->nullable(false);
            $table->tinyInteger('active')->nullable(false)->default(1);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedBigInteger('venue_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venue_types');
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('venue_type');
        });
    }
};
