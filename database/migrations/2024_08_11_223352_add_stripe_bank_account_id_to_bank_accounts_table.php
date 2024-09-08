<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('stripe_bank_account_id')->nullable()->after('bank_name');
        });
    }

    public function down()
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('stripe_bank_account_id');
        });
    }
};
