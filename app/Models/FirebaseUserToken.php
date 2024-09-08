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
        'browser_version'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
