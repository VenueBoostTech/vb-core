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
        // Venue ID for which bookings will be deleted
        $venueId = 23;



        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Fetch all booking IDs for the specified venue
        $bookingIds = DB::table('bookings')->where('venue_id', $venueId)->pluck('id');
        DB::table('bookings')->whereIn('id', $bookingIds)->delete();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No reverse operation as data deletion cannot be rolled back
    }
};
