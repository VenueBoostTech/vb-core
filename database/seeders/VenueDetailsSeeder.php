<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\VenueWhiteLabelInformation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Payroll;

class VenueDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $venueId1 = 1; // ID of the venue you want to seed details for

        $venue1 = Restaurant::find($venueId1);

        if (!$venue1) {
            $this->command->info("Venue with ID {$venueId1} not found.");
            return;
        }


        $venueWhiteLabelInfo1 = new VenueWhiteLabelInformation();

        // Set the venue details
        $venueWhiteLabelInfo1->venue_id = $venueId1; // Cuisine type ID for main cuisine
        $venueWhiteLabelInfo1->main_cuisine = 1; // Cuisine type ID for main cuisine
        $venueWhiteLabelInfo1->neighborhood = 'Lower Manhattan';
        $venueWhiteLabelInfo1->dining_style = 'Casual Dining';
        $venueWhiteLabelInfo1->dress_code = 'Casual Dress';
        $venueWhiteLabelInfo1->parking_details = 'Free Parking';
        $venueWhiteLabelInfo1->payment_options = json_encode(['Visa', 'Mastercard', 'Cash', 'Scan to Pay']);
        $venueWhiteLabelInfo1->tags = json_encode(['beers', 'events', 'birthday', 'fun']);
        $venueWhiteLabelInfo1->additional = 'birthday events';
        $venueWhiteLabelInfo1->description = 'Best restaurant in town';

        $venueWhiteLabelInfo1->save();

        $this->command->info("Venue details seeded for venue with ID {$venue1}.");


        $venueId2 = 2; // ID of the venue you want to seed details for

        $venue2 = Restaurant::find($venueId2);

        if (!$venue2) {
            $this->command->info("Venue with ID {$venue2} not found.");
            return;
        }

        $venueWhiteLabelInfo2 = new VenueWhiteLabelInformation();

        // Set the venue details
        $venueWhiteLabelInfo2->venue_id = $venueId2; // Cuisine type ID for main cuisine
        $venueWhiteLabelInfo2->neighborhood = 'Tribeka';
        $venueWhiteLabelInfo2->parking_details = 'Paid Parking';
        $venueWhiteLabelInfo2->payment_options = json_encode(['Visa', 'Mastercard', 'Cash', 'Scan to Pay']);
        $venueWhiteLabelInfo2->tags = json_encode(['arcade', 'events', 'sports']);
        $venueWhiteLabelInfo2->additional = 'all type of golf events';
        $venueWhiteLabelInfo2->main_tag = 'arcade';
        $venueWhiteLabelInfo2->field_m2 = '10000';
        $venueWhiteLabelInfo2->golf_style = 'Experience golf';
        $venueWhiteLabelInfo2->description = 'Best golf venue in town';
        $venueWhiteLabelInfo2->save();

        $this->command->info("Venue details seeded for venue with ID {$venue2}.");

        $venueId3 = 3; // ID of the venue you want to seed details for

        $venue3 = Restaurant::find($venueId3);

        if (!$venue3) {
            $this->command->info("Venue with ID {$venue3} not found.");
            return;
        }

        $venueWhiteLabelInfo3 = new VenueWhiteLabelInformation();

        // Set the venue details
        $venueWhiteLabelInfo3->venue_id = $venueId3; // Cuisine type ID for main cuisine
        $venueWhiteLabelInfo3->parking_details = 'Free Parking';
        $venueWhiteLabelInfo3->has_free_wifi = true;
        $venueWhiteLabelInfo3->has_spa = true;
        $venueWhiteLabelInfo3->has_events_hall = true;
        $venueWhiteLabelInfo3->has_gym = true;
        $venueWhiteLabelInfo3->has_restaurant = true;
        $venueWhiteLabelInfo3->hotel_type = 'Resort';
        $venueWhiteLabelInfo3->additional = 'birthday events';
        $venueWhiteLabelInfo3->wifi = 'Free wifi';
        $venueWhiteLabelInfo3->stars = '5.0';
        $venueWhiteLabelInfo3->restaurant_type = 'Asian';
        $venueWhiteLabelInfo3->room_service_starts_at = '10:00 AM';
        $venueWhiteLabelInfo3->description = 'Best hotel resort in town';
        $venueWhiteLabelInfo3->save();

        $this->command->info("Venue details seeded for venue with ID {$venue3}.");
    }
}
