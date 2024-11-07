<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirebaseUserToken extends Model
{
    protected $table = 'firebase_user_tokens';

    protected $fillable = [
        'user_id',
        'firebase_token',
        'browser_name',
        'browser_os',
        'browser_type',
        'browser_version',
         'device_id',        // Unique device identifier
        'device_type',      // ios/android
        'device_model',     // iPhone 12, Samsung S21, etc.
        'os_version',       // iOS 15.0, Android 12, etc.
        'app_version',      // Your app version
        'is_active',        // Track if token is still active
        'last_used_at'      // When token was last used
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
