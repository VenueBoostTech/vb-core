<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

}
