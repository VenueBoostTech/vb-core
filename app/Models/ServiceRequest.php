<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'client_id',
        'venue_id',
        'service_id',
        'app_project_id',
        'status',
        'priority',
        'requested_date',
        'preferred_date',
        'scheduled_date',
        'description',
        'notes',
        'assigned_to',
        'completed_at',
        'cancelled_at',
        'cancellation_reason'
    ];

    protected $casts = [
        'requested_date' => 'datetime',
        'preferred_date' => 'datetime',
        'scheduled_date' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    const STATUS_PENDING = 'Pending';
    const STATUS_SCHEDULED = 'Scheduled';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_CANCELLED = 'Cancelled';

    const PRIORITY_LOW = 'Low';
    const PRIORITY_NORMAL = 'Normal';
    const PRIORITY_HIGH = 'High';
    const PRIORITY_URGENT = 'Urgent';

    public function client(): BelongsTo
    {
        return $this->belongsTo(AppClient::class, 'client_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function appProject(): BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ServiceRequestActivity::class);
    }
}
