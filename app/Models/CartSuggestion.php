<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartSuggestion extends Model
{
    use HasFactory , softDeletes;

    protected $table = 'cart_suggestions';

    protected $fillable = [
        'cart_suggestions',
        'bybest_id',
    ];

    protected $casts = [
        'cart_suggestions' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
 
   }


   public function setCartSuggestionAttribute($value)
   {
       $this->attributes['cart_suggestions'] = json_encode($value);
   }
}
