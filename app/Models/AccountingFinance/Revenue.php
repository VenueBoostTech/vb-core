<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Revenue extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'amount',
        'revenue_date',
        'description'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'revenue_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
