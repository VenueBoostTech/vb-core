<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class AppInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_invoice_id',
        'description',
        'quantity',
        'rate',
        'amount'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2'
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AppInvoice::class);
    }
}

