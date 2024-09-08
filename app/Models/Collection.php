<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collection extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['name', 'description', 'logo_path', 'venue_id', 'slug', 'bybest_id','created_at', 'updated_at','deleted_at'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

}
