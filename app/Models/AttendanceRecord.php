<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'venue_id', 'scanned_at', 'scan_type', 'scan_method',
        'nfc_card_id', 'qr_code', 'latitude', 'longitude', 'is_within_geofence'
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'is_within_geofence' => 'boolean',
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
