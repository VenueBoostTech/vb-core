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
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->timestamps();
        });

        // Insert default values with code
        $languages = [
            [
                'name' => 'English',
                'code' => 'en'
            ],
            [
                'name' => 'Albanian',
                'code' => 'al'
            ],
            [
                'name' => 'Greek',
                'code' => 'gr'
            ],
            [
                'name' => 'French',
                'code' => 'fr'
            ],
            [
                'name' => 'German',
                'code' => 'de'
            ],
        ];

        foreach ($languages as $language) {
            DB::table('languages')->insert(['name' => $language['name'], 'code' => $language['code']]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('languages');
    }
};
