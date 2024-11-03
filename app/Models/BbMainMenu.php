<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BbMainMenu extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bb_main_menu';

    protected $fillable = [
        'bybest_id',
        'venue_id',
        'type_id',
        'group_id',
        'title',
        'photo',
        'order',
        'link',
        'focused',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'title' => 'json',
        'focused' => 'boolean',
    ];

    public function venue()
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function menuType()
    {
        return $this->belongsTo(BbMenuType::class, 'type_id', 'bybest_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function menuChildren(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BbMenuChildren::class, 'menu_id', 'bybest_id');
    }
}
