<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'construction_site_id',
        'venue_id',
        'report_type',
        'report_date',
        'description',
        'weather_conditions',
        'activities_performed',
        'issues_identified',
        'reported_by'
    ];

    protected $casts = [
        'report_date' => 'date',
        'weather_conditions' => 'array',
        'activities_performed' => 'array',
        'issues_identified' => 'array'
    ];

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }
}
