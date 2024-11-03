<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftSuggestion extends Model
{
    use HasFactory;

    protected $fillable = ['gift_occasion_id', 'product_id', 'physical_store_id'];

    public function occasion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GiftOccasion::class, 'gift_occasion_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PhysicalStore::class, 'physical_store_id');
    }
}
