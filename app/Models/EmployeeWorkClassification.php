<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWorkClassification extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'venue_id',
        'classification_type',
        'standard_rate',
        'overtime_rate',
        'standard_hours_per_week',
        'break_requirements'
    ];

    protected $casts = [
        'standard_rate' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'break_requirements' => 'array'
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
