<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySalesLcReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'venue_id',
        'report_date',
        'year',
        'month',
        'daily_sales',
        'tickets',
        'quantity',
        'ppt',
        'vpt',
        'ppp',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
