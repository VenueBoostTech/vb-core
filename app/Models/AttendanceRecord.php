<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    const SCAN_TYPE_CHECK_IN = 'check_in';
    const SCAN_TYPE_CHECK_OUT = 'check_out';

    const SCAN_METHOD_NFC = 'nfc';
    const SCAN_METHOD_QR = 'qr';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'venue_id',
        'scanned_at',
        'scan_type',
        'scan_method',
        'nfc_card_id',
        'qr_code',
        'latitude',
        'longitude',
        'is_within_geofence',
        'duration_minutes',
        'status',
        'notes',
        'approved_by'
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'is_within_geofence' => 'boolean',
        'duration_minutes' => 'integer',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    // Scopes
    public function scopeCheckIns(Builder $query): Builder
    {
        return $query->where('scan_type', self::SCAN_TYPE_CHECK_IN);
    }

    public function scopeCheckOuts(Builder $query): Builder
    {
        return $query->where('scan_type', self::SCAN_TYPE_CHECK_OUT);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('scanned_at', $date);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeWithinGeofence(Builder $query): Builder
    {
        return $query->where('is_within_geofence', true);
    }

    public function scopeOutsideGeofence(Builder $query): Builder
    {
        return $query->where('is_within_geofence', false);
    }

    // Accessors & Mutators
    public function getFormattedScannedAtAttribute(): string
    {
        return $this->scanned_at->format('Y-m-d H:i:s');
    }

    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_minutes) {
            return 'N/A';
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function getLocationAttribute(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude
        ];
    }

    // Helper Methods
    public function isCheckIn(): bool
    {
        return $this->scan_type === self::SCAN_TYPE_CHECK_IN;
    }

    public function isCheckOut(): bool
    {
        return $this->scan_type === self::SCAN_TYPE_CHECK_OUT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function approve(int $approverId): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId
        ]);
    }

    public function reject(int $approverId, string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approverId,
            'notes' => $notes
        ]);
    }

    public function getLatestPairRecord()
    {
        $query = self::where('employee_id', $this->employee_id)
            ->where('scanned_at', '<=', $this->scanned_at);

        if ($this->isCheckIn()) {
            return $query->checkOuts()->latest('scanned_at')->first();
        }

        return $query->checkIns()->latest('scanned_at')->first();
    }

    public function calculateDuration()
    {
        if ($this->isCheckIn()) {
            $checkOut = self::where('employee_id', $this->employee_id)
                ->where('scan_type', self::SCAN_TYPE_CHECK_OUT)
                ->where('scanned_at', '>', $this->scanned_at)
                ->oldest('scanned_at')
                ->first();

            if ($checkOut) {
                return $checkOut->scanned_at->diffInMinutes($this->scanned_at);
            }
        }

        return null;
    }

    // Static helper methods
    public static function getLastCheckInForEmployee(int $employeeId, string $date = null): ?self
    {
        $query = self::forEmployee($employeeId)->checkIns();

        if ($date) {
            $query->forDate($date);
        }

        return $query->latest('scanned_at')->first();
    }

    public static function getAttendanceSummary(int $employeeId, string $date): array
    {
        $records = self::forEmployee($employeeId)
            ->forDate($date)
            ->orderBy('scanned_at')
            ->get();

        $totalDuration = 0;
        $checkIns = 0;
        $checkOuts = 0;
        $irregularities = 0;

        foreach ($records as $record) {
            if ($record->isCheckIn()) {
                $checkIns++;
            } else {
                $checkOuts++;
            }

            if (!$record->is_within_geofence) {
                $irregularities++;
            }

            if ($record->duration_minutes) {
                $totalDuration += $record->duration_minutes;
            }
        }

        return [
            'total_duration' => $totalDuration,
            'formatted_duration' => sprintf('%02d:%02d', floor($totalDuration / 60), $totalDuration % 60),
            'check_ins' => $checkIns,
            'check_outs' => $checkOuts,
            'irregularities' => $irregularities,
            'records' => $records
        ];
    }
}
