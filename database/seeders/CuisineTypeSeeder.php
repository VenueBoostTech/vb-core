<?php

namespace Database\Seeders;

use App\Models\CuisineType;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Payroll;

class CuisineTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cuisineTypes = [
            'American',
            'Italian',
            'Mexican',
            'Chinese',
            'Japanese',
            'Indian',
            'Thai',
            'French',
            'Mediterranean',
            'Greek',
            'Spanish',
            'Vietnamese',
            'Korean',
            'Middle Eastern',
            'Brazilian',
            'Cajun/Creole',
            'Caribbean',
            'German',
            'Irish',
            'British',
            'African',
            'Hawaiian',
            'Australian',
            'Russian',
            'Swedish',
            'Turkish',
            'Peruvian',
            'Argentinian',
            'Colombian',
            'Portuguese',
            'Other',
        ];

        foreach ($cuisineTypes as $cuisine) {
            CuisineType::create(['name' => $cuisine]);
        }

        $this->command->info("Cuisine types seeded successfully.");
    }
}
