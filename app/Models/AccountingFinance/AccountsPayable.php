<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountsPayable extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'vendor', 'amount', 'due_date', 'status'];
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    { return $this->belongsTo(Company::class); }
}
