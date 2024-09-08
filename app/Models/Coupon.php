<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'venue_id',
        'promotion_id',
        'start_time',
        'expiry_time',
        'discount_type',
        'discount_amount',
        'minimum_spent',
        'maximum_spent',
        'usage_limit_per_coupon',
        'usage_limit_per_customer'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function promotion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function orderCoupons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderCoupon::class);
    }
}
