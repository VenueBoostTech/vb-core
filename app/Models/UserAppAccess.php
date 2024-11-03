<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAppAccess extends Model
{
    use HasFactory;

    protected $table = 'user_app_access';

    protected $fillable = ['user_id', 'app_subscription_id', 'access_granted_at', 'access_revoked_at'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appSubscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AppSubscription::class);
    }
}
