<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySyncError extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'synchronization_id',
        'stock_calculation_id',
        'venue_id',
        'error_type',
        'error_message',
        'error_context',
        'created_at'
    ];

    protected $casts = [
        'error_context' => 'array',
        'created_at' => 'datetime'
    ];

    // Define possible error types
    public const ERROR_TYPE_API = 'API';
    public const ERROR_TYPE_DATABASE = 'Database';
    public const ERROR_TYPE_VALIDATION = 'Validation';
    public const ERROR_TYPE_SYSTEM = 'System';

    public function synchronization(): BelongsTo
    {
        return $this->belongsTo(InventorySynchronization::class, 'synchronization_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    /**
     * Get formatted error message with context
     */
    public function getFormattedError(): string
    {
        $message = "[{$this->error_type}] {$this->error_message}";

        if ($this->error_context) {
            $message .= "\nContext: " . json_encode($this->error_context, JSON_PRETTY_PRINT);
        }

        return $message;
    }
}
