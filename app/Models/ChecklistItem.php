<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['hygiene_check_id', 'item', 'notes', 'is_completed','name', 'checklist_id', 'assigned_to'];

    public function hygieneCheck(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HygieneCheck::class);
    }

    public function assignedTo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
