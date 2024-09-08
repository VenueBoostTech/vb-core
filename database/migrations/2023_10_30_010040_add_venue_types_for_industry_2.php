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
                'name' => 'Arcade & Game Center',
                'short_name' => 'arcade_and_game_center',
                'definition' => 'entertainment',
                'industry_id' => 2
            ],
            [
                'name' => 'Sports Arena',
                'short_name' => 'sports_arena',
                'definition' => 'entertainment',
                'industry_id' => 2
            ],
            [
                'name' => 'Concert Hall & Theater',
                'short_name' => 'concert_hall_and_theater',
                'definition' => 'entertainment',
                'industry_id' => 2
            ],
            [
                'name' => 'Amusement & Theme Park',
                'short_name' => 'amusement_and_theme_park',
                'definition' => 'entertainment',
                'industry_id' => 2
            ],
            [
                'name' => 'Ski Resort',
                'short_name' => 'ski_resort',
                'definition' => 'entertainment',
                'industry_id' => 2
            ],
            [
                'name' => 'Museum',
                'short_name' => 'museum',
                'definition' => 'entertainment',
                'industry_id' => 2
            ],
            [
                'name' => 'Cinema',
                'short_name' => 'cinema',
                'definition' => 'entertainment',
                'industry_id' => 2
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
