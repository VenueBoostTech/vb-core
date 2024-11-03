<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;

    // TODO: we need a migration?
    protected $table = 'product_category';
    public $timestamps = false;
    protected $fillable = [
        'product_id',
        'category_id',
        'bybest_id',
        'order_product',
        'is_parent'
    ];

}
