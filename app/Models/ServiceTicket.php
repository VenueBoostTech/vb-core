<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceTicket extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING_SIGN_OFF = 'pending_sign_off';
    const STATUS_SIGNED_OFF = 'signed_off';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'ticket_number',
        'venue_id',
        'client_id',
        'service_id',
        'service_request_id',
        'app_project_id',
        'assigned_to',
        'status',
        'service_description',
        'work_performed',
        'materials_used',
        'scheduled_at',
        'started_at',
        'completed_at',
        'client_notes',
        'signature_path',
        'signed_at',
        'signed_by_name',
        'signed_by_title',
        'client_satisfied',
        'completion_checklist',
        'service_duration',
        'internal_notes'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'signed_at' => 'datetime',
        'materials_used' => 'array',
        'completion_checklist' => 'array',
        'client_satisfied' => 'boolean',
        'service_duration' => 'decimal:2'
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(AppClient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ServiceTicketPhoto::class);
    }

    public static function generateNumber(): string
    {
        $lastTicket = static::latest()->first();
        $number = $lastTicket ? intval(substr($lastTicket->ticket_number, 3)) + 1 : 1;
        return 'ST-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function isSignedOff(): bool
    {
        return !is_null($this->signed_at) && !is_null($this->signature_path);
    }

    public function isPendingSignOff(): bool
    {
        return $this->status === self::STATUS_PENDING_SIGN_OFF;
    }

    public function canBeSignedOff(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_PENDING_SIGN_OFF;
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now()
        ]);
    }

    public function recordSignOff(array $signOffData)
    {
        $this->update(array_merge($signOffData, [
            'status' => self::STATUS_SIGNED_OFF,
            'signed_at' => now()
        ]));
    }
}
