<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralLedger extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'account_name', 'account_type', 'balance'];
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    { return $this->belongsTo(Company::class); }
}
