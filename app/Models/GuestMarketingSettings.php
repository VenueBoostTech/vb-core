<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestMarketingSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_id',
        'promotion_sms_notify',
        'promotion_email_notify',
        'booking_sms_notify',
        'booking_email_notify',
    ];

    // Cast boolean fields
    protected $casts = [
        'promotion_sms_notify' => 'boolean',
        'promotion_email_notify' => 'boolean',
        'booking_sms_notify' => 'boolean',
        'booking_email_notify' => 'boolean',
    ];

    public function guest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
