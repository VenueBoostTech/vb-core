<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductGallery extends Model
{
    use HasFactory;

    // TODO: we need a migration?
    protected $table = 'product_gallery'; 
    protected $fillable = [
        'product_id',
        'bybest_id',
        'photo_name',
        'photo_description',
        'created_at',
        'updated_at',
    ];

    /**
     * Change photo name to temporary url.
     */
    protected function photoName(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }

}
