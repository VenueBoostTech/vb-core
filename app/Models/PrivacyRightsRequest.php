<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyRightsRequest extends Model
{
    use HasFactory;

    protected $table = 'privacy_rights_requests';

    protected $fillable = [
        'email_verified_at',
        'privacy_request',
        'request_contact_email',
        'request_contact_phone',
        'request_contact_name',
    ];
}
