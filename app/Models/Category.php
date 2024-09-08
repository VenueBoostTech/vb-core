<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';
    protected $fillable = ['title', 'description', 'available', 'parent_id', 'restaurant_id'];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function inventories()
    {
        return $this->belongsToMany(Inventory::class, 'inventory_category')
            ->withTimestamps();
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category');
    }

    // A category can have many subcategories.
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // A category can belong to one parent category.
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Define a relationship with the Discount model

    public function discounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Discount::class);
    }
}
