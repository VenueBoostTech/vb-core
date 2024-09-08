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
}
