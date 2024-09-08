<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'amount', 'start_date', 'end_date', 'category_allocations'];
    protected $casts = ['category_allocations' => 'array'];
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    { return $this->belongsTo(Company::class); }
}
