<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ServiceRequestActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_request_id',
        'activity_type',
        'description',
        'performed_by',
        'old_value',
        'new_value'
    ];

    const TYPE_STATUS_CHANGE = 'status_change';
    const TYPE_ASSIGNMENT = 'staff_assignment';
    const TYPE_NOTE_ADDED = 'note_added';
    const TYPE_SCHEDULE_CHANGE = 'schedule_change';

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
