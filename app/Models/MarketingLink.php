<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingLink extends Model
{
    use HasFactory;

    protected $table = 'marketing_links';

    protected $fillable = [
        'venue_id',
        'referral_code',
        'affiliate_code',
        'short_url',
        'type'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Restaurant::class);
    }
}
