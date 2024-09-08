<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueBrandProfileCustomization extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id', 'element_id', 'element_type', 'customization_key', 'customization_value'
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function element(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IndustryBrandCustomizationElement::class, 'element_id');
    }
}
