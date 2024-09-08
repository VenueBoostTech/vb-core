<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VbStoreProductVariant extends Model
{
    use HasFactory;

    use HasFactory, SoftDeletes;

    protected $table = 'vb_store_products_variants';

    protected $fillable = [
        'product_id', 'venue_id', 'variation_sku', 'article_no', 'currency_alpha', 'currency',
        'sku_alpha', 'unit_code_alpha', 'unit_code', 'tax_code_alpha', 'warehouse_id',
        'warehouse_alpha', 'last_synchronization', 'synced_at', 'synced_method',
        'product_stock_status', 'name', 'variation_image', 'sale_price', 'date_sale_start',
        'date_sale_end', 'price', 'stock_quantity', 'manage_stock', 'sell_eventually',
        'allow_back_orders', 'weight', 'length', 'width', 'height', 'product_long_description',
        'short_description'
    ];

    protected $casts = [
        'manage_stock' => 'boolean',
        'sell_eventually' => 'boolean',
        'allow_back_orders' => 'boolean',
        'last_synchronization' => 'datetime',
        'synced_at' => 'datetime',
        'date_sale_start' => 'datetime',
        'date_sale_end' => 'datetime',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function inventoryWarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class);
    }

    public function attributes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(VbStoreAttributeOption::class, 'vb_store_product_variant_attributes', 'variant_id', 'attribute_id')
            ->withPivot('venue_id')
            ->withTimestamps();
    }
}
