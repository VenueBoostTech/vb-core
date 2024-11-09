<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppInvoice extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_METHOD_CARD = 'card';
    const PAYMENT_METHOD_BANK = 'bank_transfer';
    const PAYMENT_METHOD_CASH = 'cash';

    protected $fillable = [
        'number',
        'client_id',
        'venue_id',
        'service_request_id',
        'status',
        'issue_date',
        'due_date',
        'amount',
        'tax_amount',
        'total_amount',
        'payment_method',
        'payment_due_date',
        'notes',
        'payment_terms',
        'currency',
        'stripe_payment_intent_id',
        'bank_transfer_proof',
        'bank_transfer_date'
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'due_date' => 'datetime',
        'payment_due_date' => 'datetime',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(AppClient::class, 'client_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AppInvoicePayment::class);
    }
}



