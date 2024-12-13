<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        'sale_price',
        'date_sale_start',
        'date_sale_end',
        'product_url',
        'product_type',
        'weight',
        'length',
        'width',
        'height',
        'brand_id',
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

    /**
     * Append custom columns to the model
     * 
     * @var array
     */
    protected $appends = ['bb_members_description', 'bb_members_description_al'];

    public function groups(): HasOne
    {
        return $this->hasOne(ProductGroup::class);
    }

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

    public function attribute()
    {
        return $this->hasMany(VbStoreProductAttribute::class, 'product_id', 'id');
    }

    public function productImages()
    {
        return $this->hasMany(ProductGallery::class, 'product_id', 'id');
    }

    public function postal()
    {
        return $this->belongsTo(Postal::class, 'shipping_class', 'id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
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

    /**
     * Check if the slider has a button.
     */
    protected function imagePath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }

    /**
     * Change Image path to temporary url.
     */
    protected function imageThumbnailPath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }

    // Add static column for bb members description
    protected function bbMembersDescription(): Attribute
    {
        return Attribute::make(
            get: fn($value) => '<div>
                <h1><strong>BY BEST GROUP CLIENT CARD</strong></h1></br>
                <h2><strong>What is the "By Best Group" card</strong></h2>
                <p>
                    It is a program that allows you to earn points with your purchases in our 4 stores and spend them in each of them through a single card. 
                    The "By Best Group" card is used only in the chain of stores of the company "By Best Duty Free". 
                    Through the "By Best Group" card you get discounts, gifts, promotions, and activities dedicated only to you in the stores: SWAROVSKI, SWATCH, BLUKIDS, VILLEROY & BOCH.
                </p></br>
                
                <h2><strong>How the "By Best Group" card works</strong></h2>
                <p>
                    The "By Best Group" card is not transferable to other persons not declared on the membership form.
                </p></br>
                
                <h2><strong>How to use the "By Best Group" card</strong></h2>
                <p>
                    Present the "By Best Group" card every time you make a purchase in partner stores. 
                    Collect points in each of these purchases starting from 100 ALL. For every 100 ALL spent you will earn 1 point. 
                    Accumulated points will be displayed on the purchase invoice.
                </p>
                <p>
                    Become part of the benefit clubs starting from 150 points. For every 150, 250, 500, and up to 1,000 points, 
                    you have the right to request a voucher at the box office, with which you will benefit, discounts or gifts.
                </p>
                <p>
                    In case you forget to use the card in your purchases, the points cannot be passed to you manually from the cashier to the card. 
                    The card is mandatory to use both in the purchase and in the download of points. Without the present card, no action can be performed at the checkout.
                </p>
                <p>
                    In cases where the system may be offline you do not lose any points. They will be transferred to the card the moment the connection is made. 
                    The same thing happens when you want to download your points while the system is offline. 
                    You will be able to take advantage of the coupon in a second and use it in your purchases.
                </p>
                <p>
                    The "By Best Group" card is not and is not valid as a credit card. It cannot be used for monetary transactions, but only to accumulate points 
                    and gain the privileges set for the members of the scheme.
                </p>
            </div>'
        );
    }

    // Add static column for bb members albanian description
    protected function bbMembersDescriptionAl(): Attribute
    {
        return Attribute::make(
            get: fn($value) => '<div>
                <h1><strong>KARTË KLIENTI "BY BEST GROUP"</strong></h1></br>
                <h2><strong>Çfarë është karta "By Best Group"</strong></h2>
                <p>
                    Është një program i cili ju mundëson të fitoni pikë me blerjet tuaja në 4 dyqanet tona dhe t\'i shpenzoni ato në secilën prej tyre nëpërmjet një karte të vetme. 
                    Karta "By Best Group" përdoret vetëm në rrjetin e dyqaneve të kompanisë "By Best Duty Free". 
                    Nëpërmjet kartës "By Best Group" ju përfitoni ulje, dhurata, promocione e aktivitete të dedikuara vetëm për ju në dyqanet: SWAROVSKI, SWATCH, BLUKIDS, VILLEROY & BOCH.
                </p></br>
                
                <h2><strong>Si funksionon karta "By Best Group"</strong></h2>
                <p>
                    Karta "By Best Group" nuk është e transferueshme tek persona të tjerë të padeklaruar në formularin e antarësimit.
                </p></br>
                
                <h2><strong>Si ta përdorni kartën "By Best Group"</strong></h2>
                <p>
                    Tregoni kartën "By Best Group" çdo herë që bëni blerje në dyqanet partnere. 
                    Mblidhni pikë në secilën prej këtyre blerjeve duke filluar nga 100 lekë. Për çdo 100 lekë të shpenzuara ju do të fitoni 1 pikë. 
                    Pikët e grumbulluara do të shfaqen në faturën e blerjes.
                </p>
                <p>
                    Bëhuni pjesë e klubeve të përfitimeve duke filluar nga 150 pikë. Për çdo 150, 250, 500, e deri 1.000 pikë, 
                    ju keni të drejtë të kërkoni një kupon në kasë, me të cilin do të përfitoni, uljen apo dhuratat.
                </p>
                <p>
                    Në rast se ju harroni të përdorni kartën në blerjet tuaja, pikët nuk mund t\'ju kalojnë manualisht nga kasieri në kartë. 
                    Karta është e detyrueshme të përdoret si në blerje ashtu edhe në shkarkimin e pikëve. Pa kartën prezente nuk mund të kryhet asnjë veprim në kasë.
                </p>
                <p>
                    Në rastet kur sistemi mund të jetë offline nuk humbisni asnjë pikë. Ato do të kalohen në kartë në momentin që do të realizohet lidhja. 
                    E njëjta gjë ndodh edhe kur ju do të doni të shkarkoni pikët tuaja ndërkohë që sistemi është offline. 
                    Ju do të mund të përfitoni kuponin në një moment të dytë dhe ta përdorni atë në blerjet tuaja.
                </p>
                <p>
                    Karta "By Best Group" nuk është dhe nuk vlen si një kartë krediti. Me të nuk mund të kryhen veprime monetare, 
                    por vetëm të grumbullohen pikë e të përfitohen privilegjet e përcaktuara për pjestarët e skemës.
                </p>
            </div>'
        );
    }
}
