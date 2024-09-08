<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationConfiguration extends Model
{
    use HasFactory;

    protected $table = 'notification_configuration';

    protected $fillable = [
        'name',
        'notification_type',
        'trigger_value',
        'is_active',
        'user_id'
    ];

    public function schedules(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(NotificationSchedule::class);
    }

    public function types(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(NotificationConfigurationType::class, 'config_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
