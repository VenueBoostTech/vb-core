<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueWhiteLabelInformation extends Model
{
    use HasFactory;

    protected $table = 'venue_whitelabel_information';

    protected $fillable = [
        'venue_id',
        'dining_style',
        'dress_code',
        'tags',
        'neighborhood',
        'parking_details',
        'payment_options',
        'additional',
        'field_m2',
        'golf_style',
        'main_tag',
        'has_free_wifi',
        'has_spa',
        'has_events_hall',
        'has_gym',
        'has_restaurant',
        'hotel_type',
        'wifi',
        'stars',
        'restaurant_type',
        'room_service_starts_at',
        'description',
        'delivery_fee',
        'equipment_types',
        'facilities',
        'offers_food_and_beverage',
        'offers_restaurant',
        'offers_bar',
        'offers_snackbar',
        'nr_holes',
        'advance_lane_reservation',
        'lanes',
        'amenities',
        'benefits',
        'benefit_title',
        'min_money_value',
        'max_money_value',
        'has_free_breakfast'
    ];

    public function whitelabelCustomization(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VenueWhitelabelCustomization::class, 'v_wl_information_id');
    }

}
