<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProviderRestaurant extends Model
{
    use HasFactory;

    protected $table = 'delivery_provider_restaurant';

    protected $fillable = [
        'delivery_provider_id',
        'restaurant_id',
    ];

    public function deliveryProvider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class);
    }

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
