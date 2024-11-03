<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCrossSellType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_cross_sells_type';

    protected $fillable = [
        'type',
        'description',
    ];

    public function productCrossSells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductCrossSell::class, 'type_id');
    }
}
