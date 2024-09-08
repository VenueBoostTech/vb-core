<?php

namespace Database\Seeders;

use App\Models\CuisineType;
use App\Models\Employee;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramGuest;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Payroll;

class LoyaltyProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create a loyalty program
        $loyaltyProgram = LoyaltyProgram::create([
            'venue_id' => 1,
            'title' => 'VIP Club',
            'description' => 'Join our VIP Club for exclusive benefits and rewards.',
            // Add more loyalty program details as needed
        ]);

        // Loyalty Program guests for seeding
        $guestIds = [1, 2, 3]; // Replace with the actual guest IDs

        // Associate guests with the loyalty program
        foreach ($guestIds as $guestId) {
            LoyaltyProgramGuest::create([
                'loyalty_program_id' => $loyaltyProgram->id,
                'guest_id' => $guestId,
                'created_at' => Carbon::now(),
            ]);
        }
    }
}
