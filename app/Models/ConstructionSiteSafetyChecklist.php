<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ConstructionSiteSafetyChecklist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'construction_site_id',
        'title',
        'assigned_to',
        'due_date'
    ];

    public function items()
    {
        return $this->hasMany(ConstructionSiteSafetyChecklistItem::class, 'checklist_id');
    }

    public function assigned()
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
