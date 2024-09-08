<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'venue_id',
        'address_id',
        'user_id',
        'type',
        'date_issued',
        'total_amount',
        'status',
        'payment_method',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function venue()
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
