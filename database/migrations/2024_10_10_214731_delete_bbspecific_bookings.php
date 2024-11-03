<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteBBSpecificBookings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // IDs of the bookings to be deleted
        $bookingIds = [40, 41];

        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Delete related entries in price_breakdowns table
        DB::table('price_breakdowns')->whereIn('booking_id', $bookingIds)->delete();

        // Delete related entries in receipts table
        DB::table('receipts')->whereIn('booking_id', $bookingIds)->delete();

        // Delete bookings with the specified IDs
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
        // Since this migration deletes data, reversing it is not straightforward.
        // If you need to reverse this migration, you would need to restore the data from a backup.
    }
}
