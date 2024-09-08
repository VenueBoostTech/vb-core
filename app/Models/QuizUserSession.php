<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizUserSession extends Model
{
    use HasFactory;

    protected $table = 'quiz_user_session';
    protected $fillable = [
        'quiz_id', 'correct_answers', 'user_id', 'session_id'
    ];

    public function quiz(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
