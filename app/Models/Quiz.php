<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'blog_id',
        'title'
    ];

    public function blog(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }

    public function questions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function quizResponses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizUserResponse::class);
    }

    public function quizSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizUserSession::class);
    }
}
