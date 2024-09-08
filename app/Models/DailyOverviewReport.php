<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyOverviewReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'store_id',
        'venue_id',
        'report_date',
        'year',
        'month',
        'current_year_sales',
        'last_year_sales',
        'index',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function store(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PhysicalStore::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
