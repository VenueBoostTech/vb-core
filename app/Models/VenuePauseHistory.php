<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenuePauseHistory extends Model
{
    use HasFactory;

    protected $fillable = ['venue_id', 'reason', 'start_time', 'end_time', 'reactivated_at'];

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
