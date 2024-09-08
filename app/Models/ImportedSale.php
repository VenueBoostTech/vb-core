<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportedSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'venue_id',
        'unit_type',
        'quantity_sold',
        'period',
        'start_date',
        'end_date',
        'year',
        'physical_store_id',
        'ecommerce_platform_id',
        'sale_source',
    ];


    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the product that owns the imported sale.
     */
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function physicalStore(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PhysicalStore::class);
    }

    public function ecommercePlatform(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EcommercePlatform::class);
    }
}
