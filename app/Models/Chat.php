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
        'type',
        'external_ids'
    ];

    protected $casts = [
        'external_ids' => 'json',
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

    /**
     * Get all chats for OmniStack integration
     *
     * @param int $venueId
     * @return array
     */
    public static function getChatsForOmniStack($venueId)
    {
        $chats = self::with(['endUser', 'messages' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(1);
        }])
            ->where('venue_id', $venueId)
            ->where('status', '!=', self::STATUS_DELETED)
            ->get();

        return $chats->map(function ($chat) {
            $lastMessage = $chat->messages->first();

            return [
                'id' => $chat->id,
                'end_user_id' => $chat->end_user_id,
                'end_user_name' => $chat->endUser->name ?? 'Unknown',
                'end_user_email' => $chat->endUser->email ?? null,
                'venue_user_id' => $chat->venue_user_id,
                'venue_id' => $chat->venue_id,
                'booking_id' => $chat->booking_id,
                'order_id' => $chat->order_id,
                'status' => $chat->status,
                'type' => $chat->type,
                'message_count' => $chat->messages()->count(),
                'unread_count' => $chat->messages()->where('is_read', false)
                    ->where('receiver_id', $chat->venue_user_id)
                    ->count(),
                'created_at' => $chat->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $chat->updated_at->format('Y-m-d H:i:s'),
                'last_message' => $lastMessage ? [
                    'content' => $lastMessage->content,
                    'type' => $lastMessage->type,
                    'sender_id' => $lastMessage->sender_id,
                    'created_at' => $lastMessage->created_at->format('Y-m-d H:i:s')
                ] : null
            ];
        })->toArray();
    }
}
