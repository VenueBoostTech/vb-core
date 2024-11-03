<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimesheetBreak extends Model
{
    use SoftDeletes;

    protected $table = 'app_timesheet_breaks';

    protected $fillable = [
        'timesheet_id',
        'venue_id',
        'break_start',
        'break_end',
        'break_type',
        'is_paid',
        'notes'
    ];

    protected $casts = [
        'break_start' => 'datetime',
        'break_end' => 'datetime',
        'is_paid' => 'boolean'
    ];

    public function timesheet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AppProjectTimesheet::class, 'timesheet_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
