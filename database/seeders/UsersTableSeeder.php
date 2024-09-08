<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {


        // Create a user to associate it with the restaurant
        $userID =  DB::table('users')->insertGetId([
            'name' => 'Esther Howard',
            'country_code' => 'US',
            'email' => 'e.howard@venueboost.io',
            'password' => Hash::make('Test1234!'),
        ]);

        // Create a restaurant
        DB::table('restaurants')->insert([
            'name' => 'Uproot Restaurant',
            'address' => '9 Mt Bethel Rd, Warren, NJ 07059, United States',
            'phone_number' => '(347) 282-1041',
            'email' => 'info@venueboost.io',
            'website' => 'https://venueboost.io/',
            'cuisine_type' => 'Italian',
            'open_hours' => 'Mon-Sun 11am-10pm',
            'pricing' => '$$',
            'capacity' => 50,
            'logo' => 'https://via.placeholder.com/300x300',
            'cover' => 'https://via.placeholder.com/300x300',
            'amenities' => 'Free Wi-Fi, Outdoor Seating',
            'short_code' => 'UPR01SCD',
            'app_key' => 'UPR01APP',
            'venue_type' => 1,
            'status' => 'completed',
            'user_id' => $userID,
            'is_main_venue' => 1,
        ]);

        // Create a Golf Venue
        DB::table('restaurants')->insert([
            'name' => 'Uproot Golf',
            'address' => '9 Mt Bethel Rd, Warren, NJ 07059, United States',
            'phone_number' => '(347) 282-1041',
            'email' => 'info@venueboost.io',
            'website' => 'https://www.venueboost.io/',
            'cuisine_type' => 'Italian',
            'open_hours' => 'Mon-Sun 11am-10pm',
            'pricing' => '$$',
            'capacity' => 50,
            'logo' => 'https://via.placeholder.com/300x300',
            'cover' => 'https://via.placeholder.com/300x300',
            'amenities' => 'Free Wi-Fi, Outdoor Seating',
            'short_code' => 'UPR02SCD',
            'app_key' => 'UPR02APP',
            'venue_type' => 7,
            'status' => 'completed',
            'user_id' => $userID,
            'is_main_venue' => 0,
        ]);

        // Create a Hotel
        DB::table('restaurants')->insert([
            'name' => 'Uproot Hotel',
            'address' => '9 Mt Bethel Rd, Warren, NJ 07059, United States',
            'phone_number' => '(347) 282-1041',
            'email' => 'info@venueboost.io',
            'website' => 'https://venueboost.io/',
            'cuisine_type' => 'Italian',
            'open_hours' => 'Mon-Sun 11am-10pm',
            'pricing' => '$$',
            'capacity' => 50,
            'logo' => 'https://via.placeholder.com/300x300',
            'cover' => 'https://via.placeholder.com/300x300',
            'amenities' => 'Free Wi-Fi, Outdoor Seating',
            'short_code' => 'UPR03SCD',
            'app_key' => 'UPR03APP',
            'venue_type' => 6,
            'status' => 'completed',
            'user_id' => $userID,
            'is_main_venue' => 0,
        ]);
    }
}
