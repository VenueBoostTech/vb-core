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

        // Truncate the tables
        Schema::disableForeignKeyConstraints();
        DB::table('facility_categories')->truncate();
        DB::table('facilities')->truncate();
        Schema::enableForeignKeyConstraints();

        // Define facilities for each category
        $allFacilities = [
            'Amenities' => [
                'Towels, bed sheets, soap, toilet paper, and pillows',
                'Air conditioning',
                'Central air conditioning',
                'Cleaning products',
                'Cooking basics',
                'Dedicated workspace',
                'Dishes and silverware',
                'Dryer',
                'Hair dryer',
                'Heating',
                'Hot tub',
                'Kitchen',
                'Pool',
                'TV HD',
                'Netflix',
                'Premium cable',
                'Standard cable',
                'Washer',
                'Wifi',
            ],
            'Bathroom' => [
                'Bathtub',
                'Bidet',
                'Body soap',
                'Cleaning products',
                'Conditioner',
                'Hair dryer',
                'Hot water',
                'Outdoor shower',
                'Shampoo',
            ],
            'Bedroom and laundry' => [
                'Towels, bed sheets, soap, toilet paper, and pillows',
                'Bed linens',
                'Clothing storage',
                'Dryer',
                'Drying rack for clothing',
                'Extra pillows and blankets',
                'Hangers',
                'Iron',
                'Mosquito net',
                'Room-darkening shades',
                'Safe',
                'Washer',
            ],
            'Entertainment' => [
                'Arcade games',
                'Batting cage',
                'Books and reading material',
                'Bowling alley',
                'Climbing wall',
                'Ethernet connection',
                'Exercise equipment',
                'Game console',
                'Laser tag',
                'Life size games',
                'Mini golf',
                'Movie theater',
                'Piano',
                'Ping pong table',
                'Pool table',
                'Record player',
                'Skate ramp',
                'Sound system',
                'Theme room',
                'TV HD',
                'Netflix',
                'Premium cable',
                'Standard cable',
            ],
            'Family' => [
                'Baby bath',
                'Baby monitor',
                "Children’s bikes",
                "Children's playroom",
                "Baby safety gates",
                "Board games",
                "Fireplace guards",
                "High chair",
                "Outdoor playground",
                "Outlet covers",
                "Pack ’n play/Travel crib",
                "Table corner guards",
                "Window guards",
            ],
            'Heating and cooling' => [
                'Air conditioning',
                'Central air conditioning',
                'Ceiling fan',
                'Heating',
                'Indoor fireplace',
                'Portable fans',
            ],
            'Home safety' => [
                'Carbon monoxide alarm',
                'Fire extinguisher',
                'First aid kit',
                'Smoke alarm',
            ],
            'Internet and office' => [
                'Dedicated workspace',
                'Pocket wifi',
                'Wifi',
            ],
            'Kitchen and dining' => [
                'Baking sheet',
                'Barbecue utensils',
                'Grill, charcoal, bamboo skewers/iron skewers, etc.',
                'Bread maker',
                'Blender',
                'Coffee',
                'Coffee maker',
                'Cooking basics',
                'Pots and pans, oil, salt and pepper',
                'Dining table',
                'Dishes and silverware',
                'Bowls, chopsticks, plates, cups, etc.',
                'Dishwasher',
                'Freezer',
                'Hot water kettle',
                'Kitchen',
                'Kitchenette',
                'Microwave',
                'Mini fridge',
                'Oven',
                'Refrigerator',
                'Rice maker',
                'Stove',
                'Toaster',
                'Trash compactor',
                'Wine glasses',
            ],
            'Location features' => [
                'Beach access',
                'Lake access',
                'Laundromat nearby',
                'Private entrance',
                'Resort access',
                'Ski-in/Ski-out',
                'Waterfront',
            ],
            'Outdoor' => [
                'Backyard',
                'BBQ grill',
                'Beach essentials',
                'Beach towels, umbrella, beach blanket, snorkeling gear',
                'Bikes',
                'Boat slip',
                'Fire pit',
                'Hammock',
                'Kayak',
                'Outdoor dining area',
                'Outdoor furniture',
                'Outdoor kitchen',
                'Patio or balcony',
                'Sun loungers',
            ],
            'Parking and facilities' => [
                'Elevator',
                'EV charger',
                'Free parking on premises',
                'Hockey rink',
                'Free street parking',
                'Gym',
                'Hot tub',
                'Paid parking off premises',
                'Paid parking on premises',
                'Pool',
                'Sauna',
                'Single level home',
                'No stairs in home',
            ],
            'Services' => [
                'Breakfast',
                'Breakfast is provided',
                'Cleaning available during stay',
                'Long term stays allowed (Allow stay for 28 days or more)',
                'Luggage dropoff allowed',
            ],
        ];

        // Create categories and associate facilities
        foreach ($allFacilities as $category => $facilities) {
            // Insert the category
            $categoryId = DB::table('facility_categories')->insertGetId(['name' => $category]);

            // Insert facilities associated with the category
            $facilityData = [];
            foreach ($facilities as $facility) {
                $facilityData[] = [
                    'category_id' => $categoryId,
                    'name' => $facility,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('facilities')->insert($facilityData);
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
