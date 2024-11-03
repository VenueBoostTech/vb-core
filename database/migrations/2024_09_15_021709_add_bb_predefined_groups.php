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
        $groups = [
            [1, '{"en":"WOMEN","sq": "Vajza & Gra"}', '{"en":"WOMEN","sq": "Vajza & Gra"}', '2022-03-11 09:21:33', '2023-11-13 22:20:07', null],
            [2, '{"en":"MEN", "sq": "Meshkuj"}', '{"en":"MEN", "sq": "Meshkuj"}', '2022-03-11 09:21:33', '2023-11-13 22:20:02', null],
            [3, '{"en":"KIDS", "sq": "FËMIJË"}', '{"en":"KIDS", "sq": "FËMIJË"}', '2022-03-11 09:21:33', '2023-11-13 22:19:55', null],
            [4, '{"en":"HOME", "sq": "SHTËPI"}', '{"en":"HOME", "sq": "SHTËPI"}', '2022-03-11 09:21:33', '2023-11-13 22:19:50', null],
            [5, '{"en":"SALE", "sq":"ULJE"}', '{"en":"SALE", "sq":"ULJE"}', '2022-03-11 09:21:33', '2023-11-13 22:19:43', null],
            [6, '{"en":"GIFTS", "sq":"DHURATA"}', '{"en":"GIFTS", "sq":"DHURATA"}', '2023-02-07 14:36:17', '2023-11-13 22:19:37', null],
            [7, '{"en":"Christmas", "sq": "Christmas"}', '{"en":"Christmas", "sq": "Christmas"}', '2023-11-13 22:04:44', '2023-11-13 22:04:44', null],
            [8, '{"en":"7-8 March Gifts", "sq": "Dhuratat 7-8 Mars"}', '{"en":"7-8 March Gifts", "sq": "Dhuratat 7-8 Mars"}', '2024-02-22 22:57:11', '2024-02-22 22:57:11', null],
            [9, '{"en":"99 hours / 99 products", "sq": "99 ore / 99 produkte"}', '{"en":"99 hours / 99 products", "sq": "99 ore / 99 produkte"}', '2024-02-22 22:57:11', '2024-02-22 22:57:11', null],
            [10, '{"en":"Explore Offers", "sq": "Ekspoloroni Ofertat"}', '{"en":"Explore Offers", "sq": "Ekspoloroni Ofertat"}', '2024-02-22 22:57:11', '2024-02-22 22:57:11', null],
        ];

        foreach ($groups as $group) {
            DB::table('groups')->insert([
                'bybest_id' => $group[0],
                'group_name' =>  json_decode($group[1], true)['en'],
                'description' =>json_decode($group[2], true)['en'],
                'created_at' => $group[3],
                'updated_at' => $group[4],
                'deleted_at' => $group[5],
                'venue_id' => 58, // Assuming all groups belong to venue_id 58
                'group_name_al' => json_decode($group[1], true)['sq'],
                'description_al' => json_decode($group[2], true)['sq'],
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
