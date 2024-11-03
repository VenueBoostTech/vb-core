<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventorySync extends Model
{
    use HasFactory;


    protected $fillable = ['name', 'slug'];

    public function venues()
    {
        return $this->belongsToMany(Restaurant::class, 'inventory_sync_venue', 'inventory_sync_id', 'venue_id')
            ->withPivot('last_sync_at', 'status')
            ->withTimestamps();
    }
}
