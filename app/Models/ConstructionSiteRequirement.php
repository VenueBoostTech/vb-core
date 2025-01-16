<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ConstructionSiteRequirement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'construction_site_requirements';
    protected $fillable = ['venue_id', 'construction_site_id', 'title', 'description', 'type', 'status', 'assigned_to', 'last_check_date'];

    public function constructionSite()
    {
        return $this->belongsTo(ConstructionSite::class, 'construction_site_id');
    }

    public function assigned()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }
}
