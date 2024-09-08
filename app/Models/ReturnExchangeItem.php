<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnExchangeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_exchange_id',
        'product_id',
        'quantity',
        'unit_price',
        'reason',
        'condition',
    ];

    public function returnExchange(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReturnExchange::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
