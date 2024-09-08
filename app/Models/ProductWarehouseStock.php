<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductWarehouseStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'alpha_warehouse',
        'warehouse_id',
        'stock_quantity',
        'article_no',
        'product_id',
        'venue_id',
        'last_synchronization',
        'synced_at',
        'synced_method'
    ];

    protected $casts = [
        'last_synchronization' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function inventoryWarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
