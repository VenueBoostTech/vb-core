<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisionTrackConfiguration extends Model
{
    use HasFactory;

    protected $table = 'vision_track_configurations';

    protected $fillable = [
        'ai_pipeline_id',
        'venue_id',
        'configuration_key',
        'configuration_value'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
