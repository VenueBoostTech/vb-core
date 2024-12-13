<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // $venueId = 23;

        // // Safety check to ensure we're deleting the correct data
        // $venueExists = DB::table('restaurants')->where('id', $venueId)->exists();

        // if (!$venueExists) {
        //     throw new \Exception("Venue with ID {$venueId} does not exist. Migration aborted for safety.");
        // }

        // // Disable foreign key checks
        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // try {
        //     // Delete related records
        //     DB::transaction(function () use ($venueId) {
        //         // Delete CalendarConnections
        //         DB::table('calendar_connections')->where('venue_id', $venueId)->delete();

        //         // Delete ThirdPartyBookings
        //         DB::table('third_party_bookings')->where('venue_id', $venueId)->delete();

        //         // Delete Bookings
        //         DB::table('bookings')->where('venue_id', $venueId)->delete();
        //     });
        // } finally {
        //     // Re-enable foreign key checks, even if an exception occurred
        //     DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        // }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
