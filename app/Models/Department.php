<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = ['name', 'description', 'venue_id'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(AppProject::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function departments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    public function crossTeams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

}
