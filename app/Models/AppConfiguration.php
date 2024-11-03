<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppConfiguration extends Model
{
    protected $fillable = [
        'vb_app_id',
        'venue_id',
        'app_name',
        'main_color',
        'button_color',
        'logo_url',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function vbApp(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VbApp::class);
    }
}
