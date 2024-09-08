<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueContactForm extends Model
{
    use HasFactory;
    protected $fillable = [
       'full_name',
        'phone',
        'subject',
        'email',
        'content',
        'venue_id'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
