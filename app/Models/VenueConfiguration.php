<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueConfiguration extends Model
{
    use HasFactory;

    protected $table = 'venue_configuration';

    protected $fillable = [
        'email_language',
        'stripe_connected_acc_id',
        'onboarding_completed',
        'venue_id',
        'connected_account_created_at',
        'connected_account_updated_at',
        'more_information_required'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
