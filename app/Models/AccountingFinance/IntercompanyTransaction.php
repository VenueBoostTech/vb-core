<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntercompanyTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_company_id',
        'to_company_id',
        'amount',
        'transaction_date',
        'description',
        'reconciled'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'reconciled' => 'boolean',
    ];

    public function fromCompany()
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function toCompany()
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }
}
