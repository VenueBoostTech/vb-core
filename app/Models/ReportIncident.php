<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportIncident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'construction_site_id', 
        'employee_id', 
        'venue_id', 
        'type_of_incident', 
        'date_time', 
        'location', 
        'description', 
        'person_involved', 
        'taken_action', 
        'status', 
        'photos',
        'withness_statement',
        'weather_condition',
        'lighting_condition',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'person_involved' => 'array',
    ];

    public function constructionSite()
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
