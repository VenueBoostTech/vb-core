<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteStatistic extends Model
{
    use HasFactory;

    protected $table = 'website_statistics';

    protected $fillable = [
        'faqs_screen_count'
    ];
}
