<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VbStoreAttributeOption extends Model
{
    use HasFactory;

    protected $table = 'vb_store_attributes_options';

    protected $fillable = [
        'attribute_id',
        'option_name',
        'option_name_al',
        'option_url',
        'option_description',
        'option_description_al',
        'option_photo',
        'bybest_id',
        'order_id'
    ];

    public function attribute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VbStoreAttribute::class, 'attribute_id');
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'vb_store_product_attributes', 'attribute_id', 'product_id')
            ->withPivot('venue_id')
            ->withTimestamps();
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(VbStoreProductVariant::class, 'vb_store_product_variant_attributes', 'attribute_id', 'variant_id')
            ->withPivot('venue_id')
            ->withTimestamps();
    }

    public function productVariantAttributes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VbStoreProductVariantAttribute::class, 'attribute_id');
    }
}
