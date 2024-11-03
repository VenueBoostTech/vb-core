<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VbStoreProductVariantAttribute extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vb_store_product_variant_attributes';

    protected $fillable = [
        'variant_id',
        'attribute_id',
        'venue_id',
        'bybest_id',
    ];

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VbStoreProductVariant::class, 'variant_id');
    }

    public function attributeOption(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VbStoreAttributeOption::class, 'attribute_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
