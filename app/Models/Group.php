<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;
    protected $table = 'groups';

    protected $fillable = [
        'venue_id',
        'group_name',
        'group_name_al',
        'description',
        'description_al',
        'bybest_id',
        'created_at' ,
        'updated_at' ,
    ];

    public function mainMenus(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BbMainMenu::class, 'group_id');
    }

}
