<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resource_id',
        'venue_id',
        'quantity',
        'allocated_at',
        'return_at',
        'status',
        'allocated_by'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'allocated_at' => 'datetime',
        'return_at' => 'datetime'
    ];

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'allocated_by');
    }
}
