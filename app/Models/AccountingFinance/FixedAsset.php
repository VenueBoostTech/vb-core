<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'purchase_price', 'purchase_date', 'useful_life', 'salvage_value'];
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    { return $this->belongsTo(Company::class); }
}
