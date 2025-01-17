<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentCheckInCheckOutProcess extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'venue_id',
        'employee_id',
        'type',
        'notes'
    ];

    public function photos()
    {
        return $this->hasMany(EquipmentCheckPhoto::class);
    }
}
