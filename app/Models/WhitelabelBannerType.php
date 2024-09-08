<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhitelabelBannerType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whitelabel_banner_type';

    protected $fillable = ['type', 'description'];

    protected $casts = [
        'type' => 'json',
        'description' => 'json',
    ];

    public function banners(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WhitelabelBanner::class, 'type_id');
    }
}
