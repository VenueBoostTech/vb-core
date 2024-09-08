<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'start_date', 'end_date', 'data', 'type'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'data' => 'array',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
