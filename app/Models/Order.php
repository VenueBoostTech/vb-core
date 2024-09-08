<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $fillable = [
        'total_amount',
        'total_amount_eur',
        'customer_id',
        'reservation_id',
        'restaurant_id',
        'status',
        'promotion_id',
        'discount_total',
        'discount_total_eur',
        'payment_method_id',
        'payment_status',
        'subtotal',
        'subtotal_eur',
        'stripe_payment_id',
        'notes',
        'address_id',
        'currency',
        'order_number',
        'is_for_self',
        'other_person_name',
        'delivery_fee',
        'delivery_fee_eur',
        'hospital_room_id',
        'added_by_restaurant',
        'physical_store_id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }


    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function orderIngredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    public function inventoryActivities()
    {
        return $this->hasMany(InventoryActivity::class);
    }

    public function orderDelivery()
    {
        return $this->hasOne(OrderDelivery::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function orderCoupons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderCoupon::class);
    }

    public function orderDiscounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    public function address(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function earn_points_history(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EarnPointsHistory::class);
    }

    public function statusChanges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderStatusChange::class);
    }

    public function orderSplitPayments() :\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderSplitPayment::class);
    }

    public function physicalStore(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PhysicalStore::class);
    }

}
