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
        Schema::table('pricing_and_calendar', function (Blueprint $table) {
            $table->decimal('prepayment_amount', 10, 2)->after('booking_acceptance_date')->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pricing_and_calendar', function (Blueprint $table) {
            $table->dropColumn('prepayment_amount');
        });
    }
};
