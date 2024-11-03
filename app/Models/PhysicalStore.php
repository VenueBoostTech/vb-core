<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalStore extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'venue_id',
        'address_id',
        'code'
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function address(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function importedSales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ImportedSale::class);
    }

    public function storeInventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoreInventory::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function feedback(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Feedback::class, 'store_id');
    }

    public function giftSuggestions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GiftSuggestion::class);
    }
}
