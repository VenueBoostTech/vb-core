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

        // IDs of the bookings to be deleted
        $bookingIds = [42, 43, 44, 45, 46, 47, 48, 49, 50];

        // IDs of the guests to be deleted
        $guestIds = [];

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Delete related entries in price_breakdowns table
        DB::table('price_breakdowns')->whereIn('booking_id', $bookingIds)->delete();

        // Delete related entries in receipts table
        DB::table('receipts')->whereIn('booking_id', $bookingIds)->delete();

        // Delete bookings with the specified IDs
        DB::table('bookings')->whereIn('id', $bookingIds)->delete();

        // Delete guests with the specified IDs
        DB::table('guests')->whereIn('id', $guestIds)->delete();

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
        //
    }
};
