<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityInspectionsConstructionOptionsPhoto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['qi_construction_option_id', 'photo'];

    public function qualityInspectionsConstructionOption()
    {
        return $this->belongsTo(QualityInspectionsConstructionOption::class, 'qi_construction_option_id', 'id');
    }
}
