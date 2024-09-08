<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueSubscriber extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'venue_id', 'subscribed'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(Restaurant::class);
    }
}
