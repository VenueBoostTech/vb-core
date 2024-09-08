<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'type', 'venue_id',
    ];

    public function automaticReplies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AutomaticReply::class);
    }

    public function sendable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
