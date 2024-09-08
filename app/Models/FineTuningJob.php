<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FineTuningJob extends Model
{
    use HasFactory;

    protected $table = 'fine_tuning_jobs';

    protected $fillable = ['venue_id', 'job_id'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
