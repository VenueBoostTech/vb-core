<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryWarehouse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address_id',
        'venue_id',
        'code',
        'description'
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function address(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function inventoryWarehouseProducts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryWarehouseProduct::class);
    }

    public function inventoryRetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryRetail::class, 'warehouse_id');
    }

    public function scanActivities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScanActivity::class);
    }

}
