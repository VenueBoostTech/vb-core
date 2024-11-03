<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSyncLog extends Model
{
    protected $fillable = [
        'venue_id',
        'provider',
        'sync_type',
        'payload',
        'response',
        'status',
        'error_message'
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array'
    ];

    public function venue()
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public static function getRecentFailures(int $venueId, int $limit = 10)
    {
        return static::where('venue_id', $venueId)
            ->where('status', 'failed')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public static function getProviderStats(int $venueId)
    {
        return static::where('venue_id', $venueId)
            ->selectRaw('provider,
                        COUNT(*) as total_syncs,
                        SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_syncs,
                        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_syncs')
            ->groupBy('provider')
            ->get();
    }

    public function getFormattedTimestampAttribute(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }
}
