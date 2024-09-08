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
        $data = [
            [
                'name' => 'Retail Chain',
                'short_name' => 'retail_chain',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Specialty Store',
                'short_name' => 'specialty_store',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Online Shop',
                'short_name' => 'online_shop',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Grocery Store',
                'short_name' => 'grocery_store',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Electronics Store',
                'short_name' => 'electronics_store',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Pharmacy',
                'short_name' => 'pharmacy',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Auto Parts Store',
                'short_name' => 'auto_motorcycle_parts',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Liquor Store',
                'short_name' => 'liquor_store',
                'definition' => 'retail',
                'industry_id' => 4
            ],
            [
                'name' => 'Pet Store',
                'short_name' => 'pet_store',
                'definition' => 'retail',
                'industry_id' => 4
            ],
        ];

        DB::table('venue_types')->insert($data);

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
