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
        Schema::table('countries', function (Blueprint $table) {
            $table->string('code', 2)->unique();
            $table->string('currency')->nullable();
            $table->string('main_language')->nullable();
            $table->string('other_languages')->nullable();
            $table->string('entity')->nullable();
        });

        // Create the "countries" table
        DB::table('countries')->insert([
            ['name' => 'USA', 'code' => 'US', 'currency' => 'USD', 'main_language' => 'English', 'other_languages' => null, 'entity' => null],
            ['name' => 'UK', 'code' => 'GB', 'currency' => 'GBP', 'main_language' => 'English', 'other_languages' => null, 'entity' => null],
            ['name' => 'Germany', 'code' => 'DE', 'currency' => 'EUR', 'main_language' => 'German', 'other_languages' => null, 'entity' => null],
            ['name' => 'Canada', 'code' => 'CA', 'currency' => 'CAD', 'main_language' => 'English', 'other_languages' => 'French', 'entity' => null],
            ['name' => 'Australia', 'code' => 'AU', 'currency' => 'AUD', 'main_language' => 'English', 'other_languages' => null, 'entity' => null],
            ['name' => 'Greece', 'code' => 'GR', 'currency' => 'EUR', 'main_language' => 'Greek', 'other_languages' => null, 'entity' => 'EU'],
            ['name' => 'Italy', 'code' => 'IT', 'currency' => 'EUR', 'main_language' => 'Italian', 'other_languages' => null, 'entity' => 'EU'],
            ['name' => 'Switzerland', 'code' => 'CH', 'currency' => 'CHF', 'main_language' => 'German', 'other_languages' => 'French, Italian', 'entity' => null],
            ['name' => 'Portugal', 'code' => 'PT', 'currency' => 'EUR', 'main_language' => 'Portuguese', 'other_languages' => null, 'entity' => 'EU'],
            ['name' => 'Slovenia', 'code' => 'SI', 'currency' => 'EUR', 'main_language' => 'Slovenian', 'other_languages' => null, 'entity' => 'EU'],
            ['name' => 'Serbia', 'code' => 'RS', 'currency' => 'RSD', 'main_language' => 'Serbian', 'other_languages' => null, 'entity' => null],
            ['name' => 'Poland', 'code' => 'PL', 'currency' => 'PLN', 'main_language' => 'Polish', 'other_languages' => null, 'entity' => 'EU'],
            ['name' => 'Albania', 'code' => 'AL', 'currency' => 'ALL', 'main_language' => 'Albanian', 'other_languages' => null, 'entity' => null],
            ['name' => 'Montenegro', 'code' => 'ME', 'currency' => 'EUR', 'main_language' => 'Montenegrin', 'other_languages' => 'Serbian, Bosnian, Albanian, Croatian', 'entity' => 'EU'],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('countries', function (Blueprint $table) {

            $table->dropColumn('code');
            $table->dropColumn('currency');
            $table->dropColumn('main_language');
            $table->dropColumn('other_languages');
            $table->dropColumn('entity');
        });
    }
};
