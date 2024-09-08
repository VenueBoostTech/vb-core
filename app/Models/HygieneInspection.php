<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HygieneInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id', 'inspection_date', 'inspector_name', 'observations',
        'remind_me_before_log_date_hours', 'inspection_result_status', 'next_inspection_date', 'hygiene_check_id',
        'reminder_sent'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function hygieneCheck(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HygieneCheck::class);
    }
}
