<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtDetectionActivity extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description', 'category', 'default_config', 'is_active'];

    protected $casts = [
        'default_config' => 'array',
        'is_active' => 'boolean'
    ];

    public function venueActivities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VtVenueDetectionActivity::class, 'detection_activity_id');
    }

}
