<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizUserResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'question_id', 'selected_answer_id', 'user_id', 'session_id', 'is_correct'
    ];

    public function quiz(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function question(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function answer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(QuizAnswer::class, 'selected_answer_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
