<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AppFeedback extends Model
{
    use SoftDeletes;

    protected $table = 'app_feedbacks';

    protected $fillable = [
        'venue_id',
        'client_id',
        'project_id',
        'rating',
        'comment',
        'type',
        'admin_response',
        'responded_at'
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'rating' => 'integer'
    ];

    // Constants for feedback types
    const TYPE_EQUIPMENT = 'equipment_service';
    const TYPE_PROJECT = 'project';
    const TYPE_GENERAL = 'general';

    public static function getTypes(): array
    {
        return [
            self::TYPE_EQUIPMENT => 'Equipment Service',
            self::TYPE_PROJECT => 'Project',
            self::TYPE_GENERAL => 'General'
        ];
    }

    // Relationships
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(AppClient::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'project_id');
    }

    // Scopes
    public function scopeForVenue($query, $venueId)
    {
        return $query->where('venue_id', $venueId);
    }

    public function scopeWithRating($query, $rating)
    {
        if ($rating === '3') {
            return $query->where('rating', '<=', 3);
        }
        return $query->where('rating', $rating);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeResponded($query)
    {
        return $query->whereNotNull('admin_response');
    }

    public function scopeNotResponded($query)
    {
        return $query->whereNull('admin_response');
    }

    // Helper methods
    public function isResponded(): bool
    {
        return !is_null($this->admin_response);
    }

    public function respond(string $response): void
    {
        $this->update([
            'admin_response' => $response,
            'responded_at' => Carbon::now()
        ]);
    }

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->type] ?? 'Unknown';
    }
}
