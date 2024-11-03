<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'customer_id',
        'store_id',
        'sales_associate_id',
        'visit_date',
        'overall_satisfaction',
        'product_quality',
        'staff_knowledge',
        'staff_friendliness',
        'store_cleanliness',
        'value_for_money',
        'found_desired_product',
        'product_feedback',
        'service_feedback',
        'improvement_suggestions',
        'would_recommend',
        'purchase_made',
        'purchase_amount',
        'preferred_communication_channel',
        'subscribe_to_newsletter',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'found_desired_product' => 'boolean',
        'would_recommend' => 'boolean',
        'subscribe_to_newsletter' => 'boolean',
        'purchase_amount' => 'decimal:2',
    ];

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PhysicalStore::class, 'store_id');
    }

    public function salesAssociate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_associate_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
