<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'scan_type',
        'scan_time',
        'moved_to_warehouse',
        'moved_from_warehouse',
        'venue_id'
    ];

    protected $casts = [
        'scan_time' => 'datetime',
    ];

    // Define the scan types as constants for easy reference
    const SCAN_TYPE_ADD_NEW_PRODUCT = 'add_new_product';
    const SCAN_TYPE_UPDATE_PRODUCT_INVENTORY = 'update_product_inventory';
    const SCAN_TYPE_WAREHOUSE_TRANSFER = 'warehouse_transfer';

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movedToWarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'moved_to_warehouse');
    }

    public function movedFromWarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'moved_from_warehouse');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
