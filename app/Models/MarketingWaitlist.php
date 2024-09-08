<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingWaitlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'waitlist_type',
        'venue_name',
        'phone_number',
        'country_code',
        'full_name',
        'converted_to_venue',
        'first_email_sent',
        'promo_code'
    ];
}
