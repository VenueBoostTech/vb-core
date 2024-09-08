<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'delivery_provider_id',
        'delivery_status',
        'tracking_url',
        'doordash_order_id',
        'restaurant_id',
        'external_delivery_id',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryProvider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class);
    }

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function deliveryProviderRestaurant(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeliveryProviderRestaurant::class);
    }
}
