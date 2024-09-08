<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueSurvey extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'venue_id',
        'surveymonkey_id',
        'title',
        'description',
        'scheduled_at',
        'status',
        'response_count',
        'last_sent_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'scheduled_at',
        'last_sent_at',
    ];

    /**
     * Get the venue that owns the survey.
     */
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
