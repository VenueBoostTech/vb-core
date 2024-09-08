<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSplitPayment extends Model
{
    use HasFactory;

    protected $table = 'order_split_payments';

    protected $fillable = [
        'payment_type',
        'amount',
    ];

    public function orders() :\Illuminate\Database\Eloquent\Relations\belongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
