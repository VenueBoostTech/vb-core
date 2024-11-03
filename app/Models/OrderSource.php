<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSource extends Model
{
    use HasFactory;
    protected $table = 'order_source';
    protected $fillable = [
        'source'
    ];

}
