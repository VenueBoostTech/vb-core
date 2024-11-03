<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BBLegacyCoupon extends Model
{
    use HasFactory;
    protected $table = 'bb_legacy_coupon';
    protected $fillable = [
        'bybest_id', 'coupon_code', 'coupon_amount', 'data'
    ];

}
