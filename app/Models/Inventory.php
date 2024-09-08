<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories';

    protected $fillable = ['label'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'inventory_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'inventory_category')
            ->withTimestamps();
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function inventoryAlerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryAlert::class);
    }

    public function ingredients()
    {
        return $this->belongsToMany(Product::class, 'inventory_ingredient')
            ->withPivot('quantity')
            ->withTimestamps();
    }

}
