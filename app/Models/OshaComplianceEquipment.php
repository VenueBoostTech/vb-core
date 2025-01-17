<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class OshaComplianceEquipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'osha_compliance_equipment';

    protected $fillable = [
        'venue_id', 
        'construction_site_id', 
        'title', 
        'last_inspection_date', 
        'next_inspection_date', 
        'status',
        'requirements',
        'required_actions',
        'assigned_to'
    ];

    protected $casts = [
        'requirements' => 'array',
        'required_actions' => 'array'
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function assigned()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
