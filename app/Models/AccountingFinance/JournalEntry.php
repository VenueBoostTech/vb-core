<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'entry_date', 'description', 'entries'];
    protected $casts = ['entries' => 'array'];
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    { return $this->belongsTo(Company::class); }
}
