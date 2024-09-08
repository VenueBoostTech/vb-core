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
        $languages = [
            [
                'name' => 'Italian',
                'code' => 'it'
            ],
            [
                'name' => 'Spanish',
                'code' => 'es'
            ],
        ];

        foreach ($languages as $language) {
            DB::table('languages')->insert([
                'name' => $language['name'],
                'code' => $language['code']
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
        Schema::table('languages', function (Blueprint $table) {
            DB::table('languages')->where('code', 'it')->delete();
            DB::table('languages')->where('code', 'es')->delete();
        });
    }
};
