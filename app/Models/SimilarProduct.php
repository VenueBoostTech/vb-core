<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimilarProduct extends Model
{
    use HasFactory , SoftDeletes;

    protected $table = 'similar_products';

    protected $fillable = [
        'bybest_id',
        'similar_products'
    ];

    protected $casts = [
        'similar_products' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function setSimilarProductAttribute($value)
    {
        $this->attributes['similar_products'] = json_encode($value);
    }

}
