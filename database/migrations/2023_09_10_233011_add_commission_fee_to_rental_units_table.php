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
            // add commission fee
            $table->decimal('commission_fee', 10, 2)->default(15.00)->after('unit_code');
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
            // drop commission fee
            $table->dropColumn('commission_fee');
        });
    }
};
