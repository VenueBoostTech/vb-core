<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttributeValue extends Model
{
    use HasFactory;

    protected $table = 'product_attribute_value'; // Your database table name
    protected $fillable = ['product_id', 'attribute_value_id', 'visible_on_product_page', 'used_for_variations'];

}
