<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table = 'addresses';

    protected $fillable = [
        'address_line1',
        'address_line2',
        'state',
        'city',
        'postcode',
        'country',
        'active',
        'is_for_retail',
        'latitude',
        'longitude',
        'country_id', // New attribute
        'state_id',   // New attribute
        'city_id',    // New attribute
    ];

    public function restaurants(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_addresses', 'address_id', 'restaurants_id');
    }

    public function customers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_addresses', 'address_id', 'customer_id');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Add the relationships
    public function country(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function physicalStores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PhysicalStore::class);
    }

    public function inventoryWarehouses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryWarehouse::class);
    }


    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppProject::class);
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppClient::class);
    }

}
