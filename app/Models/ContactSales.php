<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSales extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'mobile',
        'email',
        'restaurant_name',
        'restaurant_city',
        'restaurant_state',
        'restaurant_zipcode',
        'restaurant_country',
        'contact_reason',
        'is_demo',
        'status',
    ];

    public function subscribedEmails(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SubscribedEmail::class);
    }


}
