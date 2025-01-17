<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityInspectionsConstruction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quality_inspections_construction';
    protected $fillable = ['venue_id', 'app_project_id', 'inspector_id', 'location', 'inspection_type', 'signature'];

    public function qualityInspectionsConstructionOptions()
    {
        return $this->hasMany(QualityInspectionsConstructionOption::class, 'qi_construction_id', 'id');
    }
}
