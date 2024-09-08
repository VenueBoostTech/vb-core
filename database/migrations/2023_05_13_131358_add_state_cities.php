<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
                    'Manhattan', 'New York City', 'Brooklyn', 'Queens', 'The Bronx', 'Staten Island', 'Albany', 'Buffalo', 'Rochester', 'Syracuse',
                    'Yonkers', 'White Plains', 'Albion', 'Alden', 'Amherst', 'Arcade', 'Attica', 'Avon', 'Batavia', 'Blasdell',
                    'Boston', 'Brant', 'Buffalo', 'Cheektowaga', 'Clarence', 'Colden', 'Collins', 'Concord', 'Depew', 'Eden',
                    'Elma', 'Erie County', 'Evans', 'Farnham', 'Grand Island', 'Hamburg', 'Holland', 'Kenmore', 'Lackawanna', 'Lancaster',
                    'Marilla', 'Newstead', 'North Collins', 'Orchard Park', 'Sardinia', 'Tonawanda', 'Wales', 'West Seneca', 'Wilson', 'Niagara Falls',
                    'North Tonawanda', 'Lockport', 'Amherst', 'Cheektowaga', 'Lancaster', 'West Seneca', 'Depew', 'Kenmore', 'Tonawanda', 'Hamburg',
                    'Greece', 'Irondequoit', 'Webster', 'Brighton', 'Fairport', 'Penfield', 'Pittsford', 'Perinton', 'Henrietta', 'Chili',
                    'Spencerport', 'Brockport', 'Gates', 'Hilton', 'Scottsville', 'Avon', 'Mendon', 'Victor', 'Farmington', 'Canandaigua',
                    'Geneva', 'Waterloo', 'Clifton Springs', 'Palmyra', 'Newark', 'Lyons', 'Sodus', 'Fairport', 'Brighton', 'East Rochester',
                    'Pittsford', 'Honeoye Falls', 'Penfield', 'Webster', 'Ontario', 'Williamson', 'Macedon', 'Walworth', 'Marion', 'Sodus',
                    'Mumford', 'Fairport', 'Perinton', 'Pittsford', 'Mendon', 'Penfield', 'Brighton', 'Rochester', 'Henrietta', 'Scottsville',
                    'Gates', 'Chili', 'Parma', 'Greece', 'Spencerport', 'Brockport', 'Hilton', 'Bergen', 'Le Roy', 'Caledonia',
                    'Warsaw', 'Castile', 'Perry', 'Silver Springs', 'Strykersville', 'Arcade', 'Bliss', 'Delevan', 'Centerville', 'Franklinville',
                    'Ellicottville', 'Portville', 'Allegany', 'Hinsdale', 'Olean', 'Salamanca', 'Randolph', 'Lakewood', 'Jamestown', 'Bemus Point',
                    'Frewsburg', 'Panama', 'Clymer', 'Mayville', 'Dunkirk', 'Fredonia', 'Brocton', 'Westfield', 'Ripley', 'Sherman',
                    'Chautauqua', 'North Harmony', 'Ashville', 'Jamestown', 'Ellicottville', 'Great Valley', 'Carrollton', 'Little Valley', 'Mansfield', 'Salamanca',
                    'Randolph', 'Cattaraugus', 'Freedom', 'Yorkshire', 'Machias', 'Franklinville', 'Farmersville', 'Humphrey', 'Ischua', 'Gowanda', 'Dayton',
                    'Perrysburg', 'Cherry Creek', 'South Dayton', 'Gerry', 'Cassadaga', 'Forestville', 'Sinclairville', 'Charlotte', 'Clymer', 'Stockton',
                    'Villenova', 'Arkwright', 'Pomfret', 'Fredonia', 'Dunkirk', 'Sheridan', 'Silver Creek', 'Hanover', 'Forestville', 'Irving',
                    'Perrysburg', 'Versailles', 'Cattaraugus', 'Conewango', 'Napoli', 'Leon', 'Randolph', 'East Otto', 'Otto', 'Salamanca',
                    'Humphrey', 'Farmersville', 'Coldspring', 'Cuba', 'Olean', 'Ischua', 'Franklinville', 'Yorkshire', 'Machias', 'Delevan',
                    'Freedom', 'Arcade', 'Java', 'Java Center', 'Strykersville', 'Sheldon', 'Attica', 'Bennington', 'Castile', 'Bliss',
                    'Pike', 'Gainesville', 'Hunt', 'Portageville', 'Perry', 'Perry Center', 'Silver Lake', 'Silver Springs', 'Warsaw',
                    'Warsaw Village', 'Wyoming', 'Wethersfield', 'Pike', 'Arcade', 'Java', 'Java Center', 'Strykersville', 'Sheldon', 'Attica',
                    'Bennington', 'Castile', 'Bliss', 'Pike', 'Gainesville', 'Hunt', 'Portageville', 'Perry', 'Perry Center', 'Silver Lake',
                    'Silver Springs', 'Warsaw', 'Warsaw Village', 'Wyoming', 'Wethersfield', 'Geneseo', 'Avon', 'Caledonia', 'Conesus', 'Dansville',
                    'Groveland', 'Leicester', 'Lima', 'Livonia', 'Mount Morris', 'North Dansville', 'Nunda', 'Ossian', 'Portage', 'Sparta',
                    'Springwater', 'West Sparta', 'York', 'Almond', 'Alfred', 'Allen', 'Alma', 'Almond', 'Alfred', 'Allen',
                    'Alma', 'Almond', 'Amity', 'Andover', 'Angelica', 'Belfast', 'Belmont', 'Birdsall', 'Bolivar', 'Burns',
                    'Caneadea', 'Centerville', 'Clarksville', 'Cuba', 'Friendship', 'Genesee', 'Granger', 'Grove', 'Hume', 'Independence',
                    'New Hudson', 'Richburg', 'Rushford', 'Scio', 'Ward', 'Wellsville', 'West Almond', 'Willing', 'Wirt'
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
        DB::table('states')->delete();
        DB::table('cities')->delete();
    }
};
