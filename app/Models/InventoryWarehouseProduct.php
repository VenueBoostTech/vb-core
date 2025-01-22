<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryWarehouseProduct extends Model
{
    use HasFactory , SoftDeletes;

    protected $fillable = [
        'inventory_warehouse_id',
        'product_id',
        'quantity',
    ];

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'inventory_warehouse_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
