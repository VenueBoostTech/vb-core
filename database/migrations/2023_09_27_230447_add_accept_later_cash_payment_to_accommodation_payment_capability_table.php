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
        Schema::table('accommodation_payment_capability', function (Blueprint $table) {
            $table->boolean('accept_later_cash_payment')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accommodation_payment_capability', function (Blueprint $table) {
            $table->dropColumn('accept_later_cash_payment');
        });
    }
};
