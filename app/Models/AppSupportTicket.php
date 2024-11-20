<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppSupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'number',
        'client_id',
        'venue_id',
        'employee_id', // Assigned staff
        'subject',
        'description',
        'status',
        'priority',
        'app_project_id',
        'service_id',
        'service_request_id',
        'last_reply_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
    ];

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AppClient::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function assignedEmployee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppSupportTicketMessage::class, 'ticket_id');
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }

    public function service(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public static function generateNumber(): string
    {
        $lastTicket = static::latest()->first();
        $number = $lastTicket ? intval(substr($lastTicket->number, 3)) + 1 : 1;
        return 'TK-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    // Query Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }

    public function scopeForVenue($query, $venueId)
    {
        return $query->where('venue_id', $venueId);
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeAssignedTo($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('employee_id');
    }

    public function scopeRequiringResponse($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS])
            ->whereHas('messages', function($q) {
                $q->latest()
                    ->where('sender_type', 'client');
            });
    }

    // Helper Methods
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    public function isAssigned(): bool
    {
        return !is_null($this->employee_id);
    }

    public function getLastMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }

    public function requiresResponse(): bool
    {
        return $this->isActive() &&
            $this->lastMessage &&
            $this->lastMessage->sender_type === 'client';
    }

    public function getResponseTimeAttribute()
    {
        $clientMessages = $this->messages()
            ->where('sender_type', 'client')
            ->get();

        $totalResponseTime = 0;
        $responseCount = 0;

        foreach ($clientMessages as $clientMessage) {
            $nextStaffResponse = $this->messages()
                ->where('sender_type', 'employee')
                ->where('created_at', '>', $clientMessage->created_at)
                ->first();

            if ($nextStaffResponse) {
                $totalResponseTime += $nextStaffResponse->created_at->diffInSeconds($clientMessage->created_at);
                $responseCount++;
            }
        }

        return $responseCount > 0 ? $totalResponseTime / $responseCount : null;
    }
}
