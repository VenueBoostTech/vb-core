<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcommercePlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'venue_id',
        'type'
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function importedSales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ImportedSale::class);
    }


}
