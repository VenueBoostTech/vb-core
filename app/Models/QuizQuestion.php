<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'question_text'
    ];

    public function quiz(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'question_id');
    }

    public function quizResponses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizUserResponse::class);
    }
}
