<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id', 'answer_text', 'is_correct'
    ];

    public function question(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function quizResponses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizUserResponse::class);
    }
}
