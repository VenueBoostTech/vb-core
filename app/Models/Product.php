<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'image_thumbnail_path',
        'price',
        'order_method',
        'available',
        'reorder_point',
        'cost_of_goods_sold',
        'inventory_value',
        'short_description',
        'is_for_retail',
        'article_no',
        'additional_code',
        'sale_price', 'date_sale_start', 'date_sale_end', 'product_url',
        'product_type', 'weight', 'length', 'width', 'height', 'brand_id',
        'restaurant_id',
        'third_party_product_id',
        'unit_measure',
        'scan_time',
        'bybest_id',
        'featured',
        'is_best_seller',
        'product_tags',
        'product_sku',
        'sku_alpha',
        'currency_alpha',
        'tax_code_alpha',
        'price_without_tax_alpha',
        'unit_code_alpha',
        'warehouse_alpha',
        'bb_points',
        'product_status',
        'enable_stock',
        'product_stock_status',
        'sold_invidually',
        'stock_quantity',
        'low_quantity',
        'shipping_class',
        'purchase_note',
        'menu_order',
        'allow_back_order',
        'allow_customer_review',
        'syncronize_at',
        'title_al',
        'short_description_al',
        'description_al',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function category()
    {
        return $this->belongsTo('App\Category');
    }

    public function scopeInventoryByDate($query, $start_date, $end_date)
    {
        return $query->whereBetween('created_at', [$start_date, $end_date]);
    }

    public function scopeTotalSalesByDate($query, $start_date, $end_date)
    {
        return $query->whereBetween('created_at', [$start_date, $end_date])
            ->select(DB::raw("SUM(total) as total_sales"))
            ->groupBy(DB::raw("DATE(created_at)"));
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function inventories()
    {
        return $this->belongsToMany(Inventory::class, 'inventory_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_products')
            ->withPivot('product_instructions', 'product_quantity', 'product_total_price', 'product_discount_price')
            ->withTimestamps();
    }

    public function inventoryActivities()
    {
        return $this->hasMany(InventoryActivity::class);
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function inventoryRetail(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InventoryRetail::class);
    }

    public function gallery(): \Illuminate\Database\Eloquent\Relations\hasMany
    {
        return $this->hasMany(Gallery::class);
    }


    public function attributes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(VbStoreAttributeOption::class, 'vb_store_product_attributes', 'product_id', 'attribute_id')
            ->withPivot('venue_id')
            ->withTimestamps();
    }

    public function attribute() {
        return $this->hasMany(VbStoreProductAttribute::class, 'product_id', 'id');
    }

    public function galley() {
        return $this->hasMany(ProductGallery::class, 'product_id', 'id');
    }

    public function postal() {
        return $this->belongsTo(Postal::class, 'shipping_class', 'id');
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VbStoreProductVariant::class);
    }

    // Add this new method
    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category', 'product_id', 'category_id');
    }


    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function inventoryAlerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryAlert::class);
    }

    /**
     * Get the imported sales for the product.
     */
    public function importedSales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ImportedSale::class);
    }

    public function takeHome(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TakeHomeProduct::class);
    }

    public function inventoryWarehouseProducts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryWarehouseProduct::class);
    }

    public function scanActivities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScanActivity::class);
    }

    public function storeInventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoreInventory::class);
    }

    public function baseCrossSells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductCrossSell::class, 'base_product_id');
    }

    public function crossSells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductCrossSell::class, 'product_id');
    }

    public function giftSuggestions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GiftSuggestion::class);
    }
}
