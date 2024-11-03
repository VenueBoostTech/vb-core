<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Check if the currencies table exists
        if (Schema::hasTable('currencies')) {
            // Insert Albanian Lek (ALL)
            DB::table('currencies')->insert([
                'code' => 'ALL',
                'name' => 'Albanian Lek',
                'exchange_rate' => 97.50, // 1 USD = 97.50 ALL (approximate)
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert Euro (EUR)
            DB::table('currencies')->insert([
                'code' => 'EUR',
                'name' => 'Euro',
                'exchange_rate' => 0.85, // 1 USD = 0.85 EUR (approximate)
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
