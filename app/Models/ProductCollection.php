<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCollection extends Model
{
    use HasFactory;

    protected $table = 'product_collections';

    protected $fillable = [
        'product_id', 
        'collection_id', 
        'bybest_id',
    ];

}
