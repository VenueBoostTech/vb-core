<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compliance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'regulation',
        'status',
        'compliance_date',
        'notes'
    ];

    protected $casts = [
        'compliance_date' => 'date',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeByRegulation($query, $regulation)
    {
        return $query->where('regulation', $regulation);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUpcoming($query, $days = 30)
    {
        return $query->where('compliance_date', '>=', now())
            ->where('compliance_date', '<=', now()->addDays($days));
    }

    public function scopeOverdue($query)
    {
        return $query->where('compliance_date', '<', now())
            ->where('status', '!=', 'compliant');
    }

    // Methods
    public function isCompliant()
    {
        return $this->status === 'compliant';
    }

    public function isOverdue()
    {
        return $this->compliance_date < now() && !$this->isCompliant();
    }

    public function daysUntilDue()
    {
        return now()->diffInDays($this->compliance_date, false);
    }

    public function markAsCompliant()
    {
        $this->status = 'compliant';
        $this->save();
    }

    public function markAsNonCompliant()
    {
        $this->status = 'non-compliant';
        $this->save();
    }
}
