<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EquipmentUsageLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'venue_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'operator',
        'fuel_consumed',
        'performance_metrics',
        'notes'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'fuel_consumed' => 'decimal:2',
        'performance_metrics' => 'array'
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function equipmentOperator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator');
    }

    public function usageable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->started_at && $model->ended_at) {
                $model->duration_minutes = ceil($model->ended_at->diffInMinutes($model->started_at));
            }
        });
    }

    // Helper method to end the usage session
    public function endUsage($endTime = null)
    {
        $this->ended_at = $endTime ?? now();
        $this->duration_minutes = ceil($this->ended_at->diffInMinutes($this->started_at));
        $this->save();

        return $this;
    }

    // Scope for active usage sessions
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }
}
