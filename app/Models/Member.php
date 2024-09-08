<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'birthday',
        'city',
        'address',
        'preferred_brand_id',
        'accept_terms',
        'venue_id',
        'user_id',
        'registration_source',
        'utm_code',
        'accepted_at',
        'is_rejected',
        'rejection_reason',
        'rejected_at',
        'old_platform_member_code'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'birthday' => 'date',
        'accept_terms' => 'boolean',
        'accepted_at' => 'date',
        'is_rejected' => 'boolean', // Cast to boolean
        'rejected_at' => 'date', // Cast to date
    ];

    /**
     * Get the brand preferred by the member.
     */
    public function preferredBrand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Brand::class, 'preferred_brand_id');
    }

    /**
     * Get the venue associated with the member.
     */
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    /**
     * Get the user associated with the member.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
