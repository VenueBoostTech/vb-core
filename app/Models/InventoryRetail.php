<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryRetail extends Model
{
    use HasFactory;

    protected $table = 'inventory_retail';

    protected $fillable = [
        'venue_id',
        'product_id',
        'sku',
        'stock_quantity',
        'manage_stock',
        'low_stock_threshold',
        'sold_individually',
        'supplier_id',
        'article_no',
        'used_in_whitelabel',
        'used_in_stores',
        'used_in_ecommerces',
        'currency_alpha', 'currency', 'sku_alpha', 'unit_code_alpha', 'unit_code',
        'tax_code_alpha', 'warehouse_id', 'warehouse_alpha', 'last_synchronization',
        'synced_at', 'synced_method', 'product_stock_status'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany('App\Models\ActivityRetail');
    }

    public function inventoryAlerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryAlert::class);
    }

    public function inventoryWarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    protected $casts = [
        'used_in_whitelabel' => 'boolean',
        'used_in_stores' => 'array',
        'used_in_ecommerces' => 'array'
    ];
}
