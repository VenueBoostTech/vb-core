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
        Schema::table('rental_units', function (Blueprint $table) {
            $table->enum('unit_status', ['Snoozed', 'Unlisted', 'Deactivated'])->default('Unlisted');
            $table->text('guest_interaction')->nullable();
            $table->string('accommodation_type')->nullabe();
            $table->string('unit_floor')->nullable();
            $table->string('year_built')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rental_units', function (Blueprint $table) {
            $table->dropColumn(['unit_status', 'guest_interaction', 'accommodation_type', 'unit_floor', 'year_built']);
        });
    }
};
