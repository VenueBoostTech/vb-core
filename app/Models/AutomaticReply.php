<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomaticReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'tags',
        'reply_type',
        'venue_id',
    ];

    const PRE_ARRIVAL = 'pre-arrival';
    const IN_PLACE = 'in-place';
    const POST_RESERVATION = 'post-reservation';

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
