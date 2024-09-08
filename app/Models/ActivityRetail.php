<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityRetail extends Model
{
    use HasFactory;

    protected $table = 'activity_retail';

    protected $fillable = [
        'venue_id',
        'inventory_retail_id',
        'activity_type',
        'description',
        'data'
    ];

    public function inventoryRetail(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\InventoryRetail');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
