<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BbMenuChildren extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bb_menu_children';

    protected $fillable = [
        'bybest_id',
        'menu_id',
        'type_id',
        'text',
        'link',
        'order',
    ];

    protected $casts = [
        'text' => 'json',
    ];

    public function menu()
    {
        return $this->belongsTo(BbMainMenu::class, 'menu_id', 'bybest_id');
    }

    public function type()
    {
        return $this->belongsTo(BbMenuChildrenType::class, 'type_id', 'bybest_id');
    }
}
