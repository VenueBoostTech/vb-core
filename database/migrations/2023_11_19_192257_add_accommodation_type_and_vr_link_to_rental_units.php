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
            $table->enum('accommodation_venue_type', [
                'hotel',
                'cozy_retreat',
                'hostel',
                'hotel_chain',
                'vacation_rental',
                'luxury_resort',
                'family_resort',
                'service_apartment',
                'bed_and_breakfast',
                'motel',
                'capsule_hotel',
                'youth_hostel',
                'campground',
                'rv_park',
            ])->default('vacation_rental');

            $table->string('vr_link')->nullable();
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
            $table->dropColumn('accommodation_venue_type');
            $table->dropColumn('vr_link');
        });
    }
};
