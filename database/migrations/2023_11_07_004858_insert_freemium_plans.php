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
        // Add the Freemium plans
        DB::table('pricing_plans')->insert([
            [
                'short_code' => 'FF-PP-01',
                'name' => 'Freemium',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
                'category' => 'food',
            ],
            [
                'short_code' => 'FA-PP-01',
                'name' => 'Freemium',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
                'category' => 'accommodation',
            ],
            [
                'short_code' => 'FR-PP-01',
                'name' => 'Freemium',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
                'category' => 'retail',
            ],
            [
                'short_code' => 'FS-PP-01',
                'name' => 'Freemium',
                'monthly_cost' => 0,
                'yearly_cost' => 0,
                'currency' => 'USD',
                'category' => 'sport_entertainment',
            ],
        ]);
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
