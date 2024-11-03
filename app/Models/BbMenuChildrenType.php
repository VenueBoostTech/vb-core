<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BbMenuChildrenType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bb_menu_children_type';

    protected $fillable = [
        'bybest_id',
        'venue_id',
        'type',
        'description',
    ];

    protected $casts = [
        'type' => 'json',
        'description' => 'json',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function menuChildren(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BbMenuChildren::class, 'type_id', 'bybest_id');
    }
}
