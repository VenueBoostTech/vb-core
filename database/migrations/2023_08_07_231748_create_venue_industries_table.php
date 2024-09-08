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
        Schema::create('venue_industries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name');
            $table->timestamps();
        });

        // Insert data into the venue_industries table
        $data = [
            ['short_name' => 'Food', 'name' => 'Food'],
            ['short_name' => 'Sport & Entertainment', 'name' => 'Sport & Entertainment'],
            ['short_name' => 'Accommodation', 'name' => 'Accommodation'],
            ['short_name' => 'Retail', 'name' => 'Retail'],
            ['short_name' => 'Healthcare', 'name' => 'Healthcare'],
        ];

        DB::table('venue_industries')->insert($data);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('venue_industries');
    }
};
