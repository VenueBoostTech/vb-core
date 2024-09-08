<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostalPricing extends Model
{
    use HasFactory;

    protected $table = 'postal_pricing';

    protected $fillable = [
        'price',
        'price_without_tax',
        'city_id',
        'postal_id',
        'type',
        'alpha_id',
        'alpha_description',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_without_tax' => 'decimal:2',
    ];

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function postal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Postal::class);
    }
}
