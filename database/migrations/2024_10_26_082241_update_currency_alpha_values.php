<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateCurrencyAlphaValues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // First update the existing records
        DB::table('currencies')
            ->where('code', 'ALL')
            ->update(['currency_alpha' => 'LEK']);

        DB::table('currencies')
            ->where('code', 'EUR')
            ->update(['currency_alpha' => 'EUR']);

        // Then modify the column to be not nullable
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('currency_alpha')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Make the column nullable again
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('currency_alpha')->nullable()->change();
        });

        // Reset the values to null
        DB::table('currencies')
            ->whereIn('code', ['ALL', 'EUR'])
            ->update(['currency_alpha' => null]);
    }
}
