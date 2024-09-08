<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'report_date',
        'data'
    ];

    protected $casts = [
        'report_date' => 'date',
        'data' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
