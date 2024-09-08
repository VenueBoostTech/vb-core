<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCodeType extends Model
{

    use HasFactory, SoftDeletes;


    protected $fillable = ['type', 'attributes'];
    protected $casts = [
        'attributes' => 'json',
    ];

    public function promotionalCodes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PromotionalCode::class, 'type');
    }
}
