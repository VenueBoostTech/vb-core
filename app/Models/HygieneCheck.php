<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HygieneCheck extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected $fillable = [
        'venue_id', 'status', 'item', 'assigned_to', 'remind_hours_before',
        'check_date', 'type', 'reminder_sent'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function checklistItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    public function hygieneInspection(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HygieneInspection::class);
    }
}
