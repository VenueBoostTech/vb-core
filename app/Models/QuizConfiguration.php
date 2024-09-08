<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'wordcount', 
        'max_earn', 
        'earn_per_correct_answer'
    ];
    
}
