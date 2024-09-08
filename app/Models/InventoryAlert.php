<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAlert extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'inventory_retail_id',
        'alert_level',
        'inventory_id',
        'product_id',
    ];

    public function inventoryRetail(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryRetail::class);
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryAlertHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryAlertHistory::class);
    }
}
