<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('currency_alpha')->after('id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('currency_alpha');
        });
    }
};
