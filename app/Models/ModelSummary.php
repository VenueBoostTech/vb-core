<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_type',
        'summary',
    ];
}
