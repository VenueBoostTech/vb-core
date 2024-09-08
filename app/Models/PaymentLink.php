<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_type',
        'payment_structure',
        'party_size',
        'total',
        'price_per_person',
        'start_time',
        'end_time',
        'link',
        'venue_id',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
