<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use HasFactory;

    protected $table = 'store_settings';

    protected $fillable = [
        'currency',
        'venue_id',
        'enable_coupon',
        'enable_cash_payment_method',
        'enable_card_payment_method',
        'new_order_email_recipient',
        'selling_locations',
        'shipping_locations',
        'selling_location',
        'shipping_location',
        'tags',
        'neighborhood',
        'description',
        'payment_options',
        'additional',
        'main_tag',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
