<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'faq_cat_id', 'question', 'answer'
    ];

    protected $dates = ['deleted_at'];
}
