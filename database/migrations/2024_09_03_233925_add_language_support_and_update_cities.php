<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\City;
use App\Models\State;
use App\Models\Country;

return new class extends Migration
{
    public function up()
    {
        // Add language columns to cities table
        Schema::table('cities', function (Blueprint $table) {
            $table->json('name_translations')->nullable()->after('name');
        });

        // Update existing cities and add missing ones
        $albania = Country::where('name', 'Albania')->first();

        $cities = [
            ['en' => 'Elbasan', 'sq' => 'Elbasan'],
            ['en' => 'Prishtinë', 'sq' => 'Prishtinë'],
            ['en' => 'Tiranë', 'sq' => 'Tiranë'],
            ['en' => 'Durrës', 'sq' => 'Durrës'],
            ['en' => 'Vlorë', 'sq' => 'Vlorë'],
            ['en' => 'Fier', 'sq' => 'Fier'],
            ['en' => 'Gjirokastër', 'sq' => 'Gjirokastër'],
            ['en' => 'Tepelenë', 'sq' => 'Tepelenë'],
            ['en' => 'Kukës', 'sq' => 'Kukës'],
            ['en' => 'Shkodër', 'sq' => 'Shkodër'],
            ['en' => 'Berat', 'sq' => 'Berat'],
            ['en' => 'Diber', 'sq' => 'Dibër'],
            ['en' => 'Korçë', 'sq' => 'Korçë'],
            ['en' => 'Lezhë', 'sq' => 'Lezhë'],
            ['en' => 'Librazhd', 'sq' => 'Librazhd'],
            ['en' => 'Pogradec', 'sq' => 'Pogradec'],
            ['en' => 'Sarandë', 'sq' => 'Sarandë'],
            ['en' => 'Përmet', 'sq' => 'Përmet'],
            ['en' => 'Lushnjë', 'sq' => 'Lushnjë'],
            ['en' => 'Mat', 'sq' => 'Mat'],
            ['en' => 'Mirditë', 'sq' => 'Mirditë'],
            ['en' => 'Tropojë', 'sq' => 'Tropojë'],
            ['en' => 'Pukë', 'sq' => 'Pukë'],
            ['en' => 'Skrapar', 'sq' => 'Skrapar'],
            ['en' => 'Delvinë', 'sq' => 'Delvinë'],
        ];

        foreach ($cities as $cityData) {
            $city = City::firstOrCreate(
                ['name' => $cityData['en']],
                ['states_id' => State::where('country_id', $albania->id)->first()->id, 'active' => true]
            );
            $city->name_translations = json_encode($cityData);
            $city->save();
        }
    }

    public function down()
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('name_translations');
        });
    }
};
