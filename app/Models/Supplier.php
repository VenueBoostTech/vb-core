<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['name', 'venue_id'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function inventoryRetail(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryRetail::class);
    }

}
