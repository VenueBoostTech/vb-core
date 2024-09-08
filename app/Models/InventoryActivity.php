<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'product_id',
        'order_id',
        'quantity',
        'activity_type',
        'activity_category',
        'sold_at',
        'sold_at_whitelabel',
    ];

    const ORDER_SALE = 'ORDER_SALE';
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

}
