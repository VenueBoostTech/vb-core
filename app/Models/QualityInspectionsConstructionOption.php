<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityInspectionsConstructionOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['qi_construction_id', 'category', 'name', 'comment', 'status'];

    public function qualityInspectionsConstruction()
    {
        return $this->belongsTo(QualityInspectionsConstruction::class, 'qi_construction_id', 'id');
    }

    public function qualityInspectionsConstructionOptionsPhotos()
    {
        return $this->hasMany(QualityInspectionsConstructionOptionsPhoto::class, 'qi_construction_option_id', 'id');
    }
}
