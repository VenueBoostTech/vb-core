<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['title', 'description', 'logo_path', 'venue_id', 'url', 'total_stock', 'white_logo_path', 'parent_id'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function children()
    {
        return $this->hasMany(Brand::class, 'parent_id');
    }

    // A category can belong to one parent category.
    public function parent()
    {
        return $this->belongsTo(Brand::class, 'parent_id');
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Member::class, 'preferred_brand_id');
    }
}
