<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_name',
        'balance',
        'account_type'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
