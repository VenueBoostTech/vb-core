<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuspiciousActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'transaction_id',
        'description',
        'status'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
