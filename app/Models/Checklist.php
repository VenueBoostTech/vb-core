<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'items', 'project_id', 'venue_id'];

    protected $casts = [
        'items' => 'array',
    ];

    public function departments()
    {
        return $this->belongsToMany(Department::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class);
    }

    public function checklistItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
