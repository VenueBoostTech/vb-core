<?php

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        // Check if Albania exists, if not, create it
        $albania = Country::firstOrCreate(
            ['name' => 'Albania'],
            [
                'code' => 'AL',
                'active' => true,
                'currency' => 'ALL',
                'main_language' => 'Albanian',
                'other_languages' => 'Greek, Italian',
                'entity' => 'Country',
            ]
        );

        // Albanian states (prefectures) and their cities
        $prefectures = [
            'Berat' => ['Berat', 'Kuçovë', 'Poliçan'],
            'Dibër' => ['Peshkopi', 'Burrel', 'Bulqizë'],
            'Durrës' => ['Durrës', 'Shijak'],
            'Elbasan' => ['Elbasan', 'Cërrik', 'Belsh', 'Peqin', 'Gramsh', 'Librazhd'],
            'Fier' => ['Fier', 'Patos', 'Roskovec'],
            'Gjirokastër' => ['Gjirokastër', 'Libohovë', 'Tepelenë', 'Përmet'],
            'Korçë' => ['Korçë', 'Pogradec', 'Maliq', 'Ersekë'],
            'Kukës' => ['Kukës', 'Krumë'],
            'Lezhë' => ['Lezhë', 'Laç', 'Rrëshen'],
            'Shkodër' => ['Shkodër', 'Koplik', 'Pukë', 'Vau i Dejës'],
            'Tirana' => ['Tirana', 'Kamëz', 'Vorë'],
            'Vlorë' => ['Vlorë', 'Selenicë', 'Himarë', 'Sarandë', 'Konispol', 'Delvinë'],
        ];

        foreach ($prefectures as $prefectureName => $cities) {
            $prefecture = State::firstOrCreate(
                ['name' => $prefectureName, 'country_id' => $albania->id],
                ['active' => true]
            );

            foreach ($cities as $cityName) {
                City::firstOrCreate(
                    ['name' => $cityName, 'states_id' => $prefecture->id],
                    ['active' => true]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove all cities and states associated with Albania
        $albania = Country::where('name', 'Albania')->first();
        if ($albania) {
            City::whereHas('state', function ($query) use ($albania) {
                $query->where('country_id', $albania->id);
            })->delete();

            State::where('country_id', $albania->id)->delete();

            $albania->delete();
        }
    }
};
