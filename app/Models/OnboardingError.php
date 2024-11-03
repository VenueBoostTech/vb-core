<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingError extends Model
{
    use HasFactory;

    protected $fillable = [
        'potential_venue_lead_id',
        'email',
        'step',
        'error_type',
        'error_message',
        'stack_trace',
        'validation_errors',
    ];

    protected $casts = [
        'validation_errors' => 'array',
    ];

    public function potentialVenueLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PotentialVenueLead::class);
    }
}
