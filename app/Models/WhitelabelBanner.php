<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhitelabelBanner extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whitelabel_banner';

    protected $fillable = ['text', 'url', 'type_id', 'venue_id', 'status', 'timer'];

    protected $casts = [
        'text' => 'json',
        'status' => 'boolean',
        'timer' => 'datetime',
    ];

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WhitelabelBannerType::class, 'type_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
