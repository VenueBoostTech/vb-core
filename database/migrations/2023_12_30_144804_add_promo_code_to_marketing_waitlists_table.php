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
        Schema::table('marketing_waitlists', function (Blueprint $table) {
            $table->string('promo_code')->nullable()->after('country_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketing_waitlists', function (Blueprint $table) {
            $table->dropColumn('promo_code');
        });
    }
};
