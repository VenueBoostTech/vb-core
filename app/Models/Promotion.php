<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'title',
        'description',
        'type',
        'status',
        'start_time',
        'end_time',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function coupons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasMany(Campaign::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
