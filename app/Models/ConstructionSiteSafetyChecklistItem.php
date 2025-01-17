<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionSiteSafetyChecklistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'checklist_id', 
        'title', 
        'is_completed'
    ];

    public function checklist()
    {
        return $this->belongsTo(ConstructionSiteSafetyChecklist::class);
    }
}
