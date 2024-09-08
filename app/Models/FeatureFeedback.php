<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFeedback extends Model
{
    use HasFactory;

    protected $table = 'feature_feedbacks';

    protected $fillable = [
        'venue_id',
        'feature_name',
        'question_1',
        'question_2',
        'question_3',
        'question_1_answer',
        'question_2_answer',
        'question_3_answer',
        'additional_info_1',
        'additional_info_2',
        'additional_info_3',
    ];

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
