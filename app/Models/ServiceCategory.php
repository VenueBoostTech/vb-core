<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'name',
        'slug',
        'description'
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function activeServices()
    {
        return $this->services()->where('status', 'Active');
    }
}

