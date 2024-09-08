<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxCalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'event_name', 'due_date', 'description'];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
