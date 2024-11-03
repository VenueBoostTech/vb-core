<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'items'];

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
}
