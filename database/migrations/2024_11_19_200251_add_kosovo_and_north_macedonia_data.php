<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddKosovoAndNorthMacedoniaData extends Migration
{
    public function up()
    {
        // Get existing unspecified country ID if exists
        $unspecifiedCountryId = DB::table('countries')->where('code', 'XX')->value('id');

        // If unspecified doesn't exist, create it
        if (!$unspecifiedCountryId) {
            DB::table('countries')->insert([
                'name' => 'Unspecified',
                'code' => 'XX',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $unspecifiedCountryId = DB::getPdo()->lastInsertId();
        }

        // Check if unspecified state exists
        $unspecifiedStateId = DB::table('states')
            ->where('country_id', $unspecifiedCountryId)
            ->where('name', 'Unspecified')
            ->value('id');

        if (!$unspecifiedStateId) {
            DB::table('states')->insert([
                'country_id' => $unspecifiedCountryId,
                'name' => 'Unspecified',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $unspecifiedStateId = DB::getPdo()->lastInsertId();
        }

        // Check if unspecified city exists
        $unspecifiedCityExists = DB::table('cities')
            ->where('states_id', $unspecifiedStateId)
            ->where('name', 'Unspecified')
            ->exists();

        if (!$unspecifiedCityExists) {
            DB::table('cities')->insert([
                'states_id' => $unspecifiedStateId,
                'name' => 'Unspecified',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Check if Kosovo exists before adding
        if (!DB::table('countries')->where('code', 'XK')->exists()) {
            DB::table('countries')->insert([
                'name' => 'Kosovo',
                'code' => 'XK',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $kosovoId = DB::getPdo()->lastInsertId();

            $kosovoDistricts = [
                'Pristina' => ['Pristina', 'Podujevo', 'Obilic', 'Lipjan'],
                'Prizren' => ['Prizren', 'Dragash', 'Suva Reka'],
                'Peja' => ['Peja', 'Deçan', 'Klina'],
                'Mitrovica' => ['Mitrovica', 'Skenderaj', 'Vushtrri'],
                'Gjakova' => ['Gjakova', 'Rahovec', 'Junik'],
                'Ferizaj' => ['Ferizaj', 'Kaçanik', 'Shterpce'],
                'Gjilan' => ['Gjilan', 'Kamenica', 'Vitina']
            ];

            foreach ($kosovoDistricts as $district => $cities) {
                DB::table('states')->insert([
                    'country_id' => $kosovoId,
                    'name' => $district,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stateId = DB::getPdo()->lastInsertId();

                foreach ($cities as $city) {
                    DB::table('cities')->insert([
                        'states_id' => $stateId,
                        'name' => $city,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Check if North Macedonia exists before adding
        if (!DB::table('countries')->where('code', 'MK')->exists()) {
            DB::table('countries')->insert([
                'name' => 'North Macedonia',
                'code' => 'MK',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $macedoniaId = DB::getPdo()->lastInsertId();

            $macedoniaRegions = [
                'Skopje' => ['Skopje', 'Aerodrom', 'Centar', 'Karpos'],
                'Vardar' => ['Veles', 'Negotino', 'Kavadarci'],
                'East' => ['Stip', 'Kocani', 'Vinica'],
                'Southwest' => ['Ohrid', 'Struga', 'Debar'],
                'Southeast' => ['Strumica', 'Radovis', 'Gevgelija'],
                'Pelagonia' => ['Bitola', 'Prilep', 'Resen'],
                'Polog' => ['Tetovo', 'Gostivar', 'Mavrovo'],
                'Northeast' => ['Kumanovo', 'Kriva Palanka', 'Kratovo']
            ];

            foreach ($macedoniaRegions as $region => $cities) {
                DB::table('states')->insert([
                    'country_id' => $macedoniaId,
                    'name' => $region,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stateId = DB::getPdo()->lastInsertId();

                foreach ($cities as $city) {
                    DB::table('cities')->insert([
                        'states_id' => $stateId,
                        'name' => $city,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        // First get the IDs
        $macedoniaId = DB::table('countries')->where('code', 'MK')->value('id');
        $kosovoId = DB::table('countries')->where('code', 'XK')->value('id');

        if ($macedoniaId) {
            $macedoniaStateIds = DB::table('states')->where('country_id', $macedoniaId)->pluck('id');
            DB::table('cities')->whereIn('states_id', $macedoniaStateIds)->delete();
            DB::table('states')->where('country_id', $macedoniaId)->delete();
            DB::table('countries')->where('code', 'MK')->delete();
        }

        if ($kosovoId) {
            $kosovoStateIds = DB::table('states')->where('country_id', $kosovoId)->pluck('id');
            DB::table('cities')->whereIn('states_id', $kosovoStateIds)->delete();
            DB::table('states')->where('country_id', $kosovoId)->delete();
            DB::table('countries')->where('code', 'XK')->delete();
        }

        // Don't remove Unspecified as it might be used by other parts of the system
    }
}
