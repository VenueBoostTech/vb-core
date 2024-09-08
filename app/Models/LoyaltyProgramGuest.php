<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyProgramGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'loyalty_program_id',
        'guest_id',
    ];

    public function loyaltyProgram(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function guest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }
}
