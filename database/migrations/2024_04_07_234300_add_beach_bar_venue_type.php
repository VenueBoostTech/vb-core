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
        $beachBarType = [
            'name' => 'Beach Bar',
            'short_name' => 'beach_bar',
            'industry_id' => 2, // Entertainment Venue industry
            'definition' => 'sport_entertainment',
        ];

        // Insert the new venue type
        DB::table('venue_types')->insert($beachBarType);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove the beach bar venue type
        DB::table('venue_types')->where('short_name', 'beach_bar')->delete();
    }
};
