<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'end_user_id',     // nullable, for old system
        'venue_user_id',   // nullable, for old system
        'venue_id',
        'booking_id',
        'project_id',      // for staff/client chats
        'sender_id',       // for staff/client chats
        'receiver_id',     // for staff/client chats
        'status',
        'order_id',
        'type'
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_DELETED = 'deleted';

    const TYPE_ORDER = 'order';     // old system
    const TYPE_BOOKING = 'booking'; // old system
    const TYPE_STAFF = 'staff';     // new system - employee to employee
    const TYPE_CLIENT = 'client';   // new system - operations manager to app client

    public function endUser()
    {
        return $this->belongsTo(User::class, 'end_user_id');
    }

    public function venueUser()
    {
        return $this->belongsTo(User::class, 'venue_user_id');
    }

    public function sender(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function project()
    {
        return $this->belongsTo(AppProject::class, 'project_id');
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
