<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'enabled',
    ];

    public function restaurants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'email_configuration_venue', 'email_configuration_id', 'venue_id');
    }
}
