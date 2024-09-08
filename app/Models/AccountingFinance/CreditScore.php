<?php

namespace App\Models\AccountingFinance;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'credit_score',
        'last_updated'
    ];

    protected $casts = [
        'last_updated' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
