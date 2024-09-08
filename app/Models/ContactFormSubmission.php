<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'message',
    ];

    public function subscribedEmails(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SubscribedEmail::class);
    }
}
