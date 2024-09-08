<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'steps',
        'status'
    ];

    protected $casts = [
        'steps' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
