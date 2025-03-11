<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'title',
        'description',
        'type',
        'status',
        'start_time',
        'end_time',
        'external_ids'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'status' => 'boolean',
        'external_ids' => 'json',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function coupons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasMany(Campaign::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all promotions for OmniStack integration
     *
     * @param int $venueId
     * @return array
     */
    public static function getPromotionsForOmniStack($venueId)
    {
        $promotions = self::with(['discounts'])
            ->where('venue_id', $venueId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $promotions->map(function ($promotion) {
            $discounts = $promotion->discounts->map(function ($discount) {
                return [
                    'id' => $discount->id,
                    'type' => $discount->type,
                    'value' => $discount->value,
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
                ];
            })->toArray();

            return [
                'id' => $promotion->id,
                'venue_id' => $promotion->venue_id,
                'title' => $promotion->title,
                'description' => $promotion->description,
                'type' => $promotion->type,
                'status' => (bool) $promotion->status,
                'start_time' => $promotion->start_time ? $promotion->start_time->format('Y-m-d H:i:s') : null,
                'end_time' => $promotion->end_time ? $promotion->end_time->format('Y-m-d H:i:s') : null,
                'created_at' => $promotion->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $promotion->updated_at->format('Y-m-d H:i:s'),
                'discounts' => $discounts
            ];
        })->toArray();
    }
}
