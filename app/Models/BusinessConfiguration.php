<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'show_end_of_day_automatically',
        'end_of_day_date',
        'user_id'
    ];
}
