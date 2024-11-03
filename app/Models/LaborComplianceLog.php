<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaborComplianceLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'venue_id',
        'timesheet_id',
        'event_type',
        'severity',
        'description',
        'details'
    ];

    protected $casts = [
        'details' => 'array'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function timesheet()
    {
        return $this->belongsTo(AppProjectTimesheet::class, 'timesheet_id');
    }

    public function venue()
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
