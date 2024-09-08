<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigitalMenu extends Model
{
    use HasFactory;

    protected $table = 'digital_menus';

    protected $fillable = ['menu_data', 'restaurant_id'];

    protected $casts = [
        'menu_data' => 'array',
    ];

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
