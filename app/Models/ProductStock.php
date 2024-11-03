<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStock extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'product_stock';
    protected $fillable = [
        'article_no',
        'stock_quantity',
        'alpha_warehouse',
        'synchronize_at',
        'alpha_date',
        'bybest_id',
        'venue_id',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'synchronize_at' => 'datetime',
        'alpha_date' => 'datetime',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
