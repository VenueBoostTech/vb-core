<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCheckInOutMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'check_in_out_method_id',
    ];
}
