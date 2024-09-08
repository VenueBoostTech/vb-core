<?php

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
        $states = [
            [
                'name' => 'New York',
                'cities' => [
                    'Hempstead town',
                    'Brookhaven',
                    'Islip',
                    'Oyster Bay',
                    'North Hempstead',
                    'Babylon town',
                    'Huntington',
                    'Ramapo',
                    'Smithtown',
                    'Greece',
                    'Greenburgh',
                    'Clarkstown',
                    'Colonie town',
                    'Tonawanda town',
                    'Southampton town',
                    'Union',
                    'Troy',
                    'Irondequoit',
                    'Rye town',
                    'Orangetown',
                    'Henrietta',
                    'Poughkeepsie town',
                    'Webster town',
                    'Lancaster town',
                    'Mount Pleasant',
                    'Freeport',
                    'Cortlandt',
                    'Valley Stream',
                    'Kiryas Joel', // You can choose this or the next one or both
                    'Palm Tree', // You can choose this or the previous one or both
                    'Penfield',
                    'Ossining town',
                    'Haverstraw town',
                    'Clifton Park',
                    'Guilderland',
                    'Brighton town',
                    'Riverhead',
                    'Yorktown',
                    'Bethlehem',
                    'Long Beach',
                    'Carmel',
                    'Eastchester',
                    'Manlius town',
                    'Clarence',
                    'Spring Valley',
                    'Ithaca city',
                    'Salina',
                    'Poughkeepsie city',
                    'Newburgh town',
                    'Warwick town',
                    'Rome',
                    'Wallkill',
                    'Cicero',
                    'Vestal',
                    'Mamaroneck town',
                    'Port Chester',
                    'Rotterdam',
                    'Pittsford town',
                    'North Tonawanda',
                    'Middletown city',
                    'East Fishkill',
                    'Orchard Park town',
                    'Harrison',
                    'Queensbury',
                    'Glenville',
                    'Chili',
                    'Gates',
                    'East Hampton town',
                    'Saratoga Springs',
                    'Newburgh city',
                    'Wappinger',
                    'Jamestown',
                    'Glen Cove',
                    'New Windsor',
                    'Lindenhurst',
                    'Ossining village',
                    'Halfmoon',
                    'Le Ray',
                    'Auburn',
                    'Elmira city',
                    'Rockville Centre',
                    'Peekskill',
                    'De Witt',
                    'Camillus town',
                    'Watertown city',
                    'Kingston city',
                    'Southold',
                    'Fishkill town',
                    'Niskayuna',
                    'Montgomery town',
                    'Lysander',
                    'Garden City',
                    'Onondaga',
                    'New Hartford town',
                    'Ithaca town',
                    'Grand Island',
                    'Monroe town',
                    'Mineola',
                    'Hyde Park',
                    'Somers',
                    'Lockport town',
                    'Lockport city',
                    'Ogden',
                    'Lynbrook',
                    'Plattsburgh city',
                    'Lackawanna',
                    'Mamaroneck village',
                    'Saugerties town',
                    'Milton',
                    'Horseheads town',
                    'Owego town',
                    'Blooming Grove',
                    'Wheatfield',
                    'Amsterdam city',
                    'Southeast',
                    'Cohoes',
                    'Whitestown',
                    'Scarsdale',
                    'New Castle',
                    'Wilton',
                    'Malta',
                    'Cortland',
                    'Oswego city',
                    'Bedford',
                    'Massapequa Park',
                    'Geddes',
                    'Moreau',
                    'Thompson',
                    'East Greenbush',
                    'LaGrange',
                    'Rye city',
                    'Parma',
                    'Victor town',
                    'Floral Park',
                    'Lewiston town',
                    'Westbury',
                    'Oneonta city',
                    'Batavia city',
                    'New Paltz town',
                    'Evans',
                    'Johnson City',
                    'Kenmore',
                    'Tonawanda city', // You can choose this or the next one or both
                    'Depew', // You can choose this or the previous one or both
                    'Potsdam town',
                    'Gloversville',
                    'Farmington',
                    'Stony Point',
                    'Sullivan',
                    'Fallsburg',
                    'Glens Falls',
                    'Goshen town',
                    'Beacon',
                    'Van Buren',
                    'Beekman',
                    'Aurora town',
                    'Olean city',
                    'Arcadia',
                    'Dryden town',
                    'Shawangunk',
                    'Endicott',
                    'North Greenbush',
                    'Sweden',
                    'Kingsbury',
                    'Kent',
                    'Schodack',
                    'Pomfret',
                    'Highlands',
                    'Pelham town',
                    'Cornwall',
                    'Mamakating',
                    'Ulster',
                    'Chester town',
                    'Dunkirk city',
                    'Ballston',
                    'Geneva city',
                    'Brunswick',
                    'Wawarsing',
                    'Massena town',
                    'Patchogue',
                    'Haverstraw village',
                    'Woodbury town',
                    'German Flatts',
                    'Babylon village',
                    'North Castle',
                    'Lewisboro',
                    'Plattsburgh town',
                    'Putnam Valley',
                    'Lansing town',
                    'Tarrytown',
                    'Elma',
                    'Canton town',
                    'Patterson',
                    'Woodbury village',
                    'Catskill town',
                    'Bath town',
                    'Suffern',
                    'Dobbs Ferry',
                    'Fulton city',
                    'Malone town',
                    'Canandaigua town',
                    'Lloyd',
                    'Lake Grove',
                    'Great Neck',
                    'Sleepy Hollow',
                    'Chenango',
                    'Corning city',
                    'Mount Kisco',
                    'West Haverstraw',
                    'Ontario',
                ]

            ]
        ];


        foreach ($states as $key => $state) {
            $stateId = DB::table('states')->insertGetId([
                'name' => $state['name']
            ]);

            foreach ($state['cities'] as $key => $city) {
                DB::table('cities')->insert([
                    'name' => $city,
                    'states_id' => $stateId
                ]);
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

    }
};
