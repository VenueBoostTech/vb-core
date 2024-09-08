<?php

namespace App\Models\AccountingFinance;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'af_invoices';

    protected $fillable = [
        'company_id',
        'invoice_number',
        'customer_id',
        'issue_date',
        'due_date',
        'total_amount',
        'status'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->where('status', '!=', 'paid');
    }
}
