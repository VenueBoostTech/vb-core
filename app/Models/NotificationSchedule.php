<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    use HasFactory;

    protected $table = 'notification_schedule';

    protected $fillable = [
        'user_id',
        'notification_configuration_id',
        'send_at',
        'is_sent'
    ];

    public function configuration()
    {
        return $this->belongsTo(NotificationConfiguration::class, 'notification_configuration_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
