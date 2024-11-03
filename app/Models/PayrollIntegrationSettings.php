<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollIntegrationSettings extends Model
{
    protected $fillable = [
        'venue_id',
        'provider',
        'environment',
        'credentials',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    public function venue()
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
