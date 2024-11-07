<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('chats', function (Blueprint $table) {
            // Add order_id if it doesn't exist
            if (!Schema::hasColumn('chats', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable();
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            }

            // Add booking_id
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');

            // Add chat type column
            $table->enum('type', ['order', 'booking'])->nullable();
        });
    }

    public function down()
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn('booking_id');
            $table->dropColumn('type');
        });
    }
};
