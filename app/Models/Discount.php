<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'promotion_id',
        'title',
        'value',
        'type',
        'status',
        'start_time',
        'end_time',
        'reservation_count',
        'product_id',
        'rental_unit_id',
        'apply_for',
        'category_id',  // Add category_id to $fillable
        'product_ids',  // Add product_ids to $fillable
        'usage_limit_per_coupon',
        'usage_limit_per_customer',
        'coupon_use',
        'user_id',
        'minimum_spent',
        'selected_product',
        'external_ids'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'status' => 'boolean',
        'value' => 'float',
        'external_ids' => 'json',
    ];


    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function promotion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rental_unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public function order_discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    // Define a relationship with the Product model for the associated products
    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'discount_product', 'discount_id', 'product_id');
    }

    // Define a relationship with the Category model
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function selectedProduct(){
        return $this->belongsTo(Product::class, 'selected_product');
    }

    public function order(){
        return $this->hasMany(Order::class);
    }

    /**
     * Get all discounts for OmniStack integration
     *
     * @param int $venueId
     * @return array
     */
    public static function getDiscountsForOmniStack($venueId)
    {
        $discounts = self::with(['promotion', 'product', 'category', 'rental_unit'])
            ->where('venue_id', $venueId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $discounts->map(function ($discount) {
            return [
                'id' => $discount->id,
                'venue_id' => $discount->venue_id,
                'promotion_id' => $discount->promotion_id,
                'type' => $discount->type,
                'value' => (float) $discount->value,
                'start_time' => $discount->start_time->format('Y-m-d H:i:s'),
                'end_time' => $discount->end_time->format('Y-m-d H:i:s'),
                'status' => (bool) $discount->status,
                'product_id' => $discount->product_id,
                'rental_unit_id' => $discount->rental_unit_id,
                'category_id' => $discount->category_id,
                'product_ids' => $discount->product_ids,
                'reservation_count' => $discount->reservation_count,
                'apply_for' => $discount->apply_for,
                'minimum_spent' => $discount->minimum_spent,
                'user_id' => $discount->user_id,
                'usage_limit_per_coupon' => $discount->usage_limit_per_coupon,
                'usage_limit_per_customer' => $discount->usage_limit_per_customer,
                'coupon_use' => $discount->coupon_use,
                'selected_product' => $discount->selected_product,
                'created_at' => $discount->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $discount->updated_at->format('Y-m-d H:i:s'),
                'promotion' => $discount->promotion ? [
                    'id' => $discount->promotion->id,
                    'title' => $discount->promotion->title,
                    'description' => $discount->promotion->description,
                    'type' => $discount->promotion->type,
                    'status' => (bool) $discount->promotion->status,
                ] : null,
                'product' => $discount->product ? [
                    'id' => $discount->product->id,
                    'title' => $discount->product->title,
                    'price' => $discount->product->price,
                ] : null,
                'category' => $discount->category ? [
                    'id' => $discount->category->id,
                    'name' => $discount->category->name,
                ] : null,
                'rental_unit' => $discount->rental_unit ? [
                    'id' => $discount->rental_unit->id,
                    'name' => $discount->rental_unit->name,
                ] : null,
            ];
        })->toArray();
    }
}
