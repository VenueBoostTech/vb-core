<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = ['region', 'zone_name', 'venue_id'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function shippingMethods(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'shipping_zone_method_pivot')->withPivot('has_minimum_order_amount', 'flat_rate_cost', 'minimum_order_amount');
    }
}
