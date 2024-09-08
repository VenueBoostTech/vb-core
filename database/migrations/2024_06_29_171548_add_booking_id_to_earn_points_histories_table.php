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
        Schema::table('earn_points_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->after('reservation_id');

            // Add foreign key constraint for booking_id
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('earn_points_histories', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn('booking_id');
        });
    }
};
