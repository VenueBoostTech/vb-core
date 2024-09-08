<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Generate API key and secret
        $apiKey = Str::random(32);
        $apiSecret = Str::random(64);

        // Insert a record for Monday.com app
        DB::table('api_apps')->insert([
            'name' => 'Monday.com',
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'usage_count' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('api_apps', function (Blueprint $table) {
            //
        });
    }
};
