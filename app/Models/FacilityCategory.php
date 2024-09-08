<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacilityCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function facilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Facility::class, 'category_id', 'id');
    }
}
