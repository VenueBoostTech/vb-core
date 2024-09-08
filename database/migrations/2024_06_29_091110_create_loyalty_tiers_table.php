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
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('min_stays');
            $table->integer('max_stays');
            $table->integer('period_up')->default(12); // in days
            $table->integer('period_down')->default(6); // in days
            $table->float('discount')->default(0);
            $table->boolean('free_breakfast')->default(false);
            $table->boolean('free_room_upgrade')->default(false);
            $table->boolean('priority_support')->default(false);
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
        Schema::dropIfExists('loyalty_tiers');
    }
};
