<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function orderIngredients()
    {
        return $this->hasMany(OrderIngredient::class);
    }

    public function inventories(): \Illuminate\Database\Eloquent\Relations\belongsToMany
    {
        return $this->belongsToMany(Inventory::class, 'inventory_ingredient')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\belongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_ingredients')
            ->withPivot('ingredient_instructions', 'ingredient_quantity', 'ingredient_total_price')
            ->withTimestamps();
    }

    //other relations to be decided
}
