<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Brand extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'logo_path',
        'venue_id',
        'url',
        'total_stock',
        'white_logo_path',
        'parent_id',
        'bybest_id',
        'sidebar_logo_path',
        'short_description',
        'short_description_al',
        'description_al',
        'keywords',
        'more_info',
        'brand_order_no',
        'status_no',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

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

    /**
     * Change Image path to temporary url.
     */
    protected function logoPath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }

    /**
     * Change Image path to temporary url.
     */
    protected function whiteLogoPath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }

    /**
     * Change Image path to temporary url.
     */
    protected function sidebarLogoPath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }
}
