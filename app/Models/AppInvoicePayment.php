<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppInvoicePayment extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'app_invoice_id',
        'amount',
        'payment_method',
        'status',
        'transaction_id',
        'payment_date',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'metadata' => 'array'
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AppInvoice::class);
    }
}
