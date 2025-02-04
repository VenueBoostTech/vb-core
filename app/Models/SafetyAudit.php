<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Employee;
use App\Models\OshaComplianceEquipment;

class SafetyAudit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'safety_audit';

    protected $fillable = [
        'venue_id', 
        'construction_site_id', 
        'osha_compliance_id',
        'ppe_compliance', 
        'fall_protection', 
        'key_findings', 
        'status',
        'audited_at', 
        'audited_by',
        'score'
    ];

    public function oshaCompliance()
    {
        return $this->belongsTo(OshaComplianceEquipment::class, 'osha_compliance_id');
    }

    public function audited()
    {
        return $this->belongsTo(Employee::class, 'audited_by');
    }
}
