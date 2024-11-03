<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySynchronization extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'venue_id',
        'sync_type',
        'method',
        'third_party',
        'created_at',
        'completed_at',
        'failed_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    protected $dates = [
        'created_at',
        'completed_at',
        'failed_at'
    ];

    // Define possible sync methods
    public const METHOD_CSV_IMPORT = 'csv_import';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_API_CRONJOB = 'api_cronjob';

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function syncType(): BelongsTo
    {
        return $this->belongsTo(InventorySync::class, 'sync_type');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(InventorySyncError::class, 'synchronization_id');
    }

    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    public function isFailed(): bool
    {
        return !is_null($this->failed_at);
    }

    public function isPending(): bool
    {
        return is_null($this->completed_at) && is_null($this->failed_at);
    }
}
