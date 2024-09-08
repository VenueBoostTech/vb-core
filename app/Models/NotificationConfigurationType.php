<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationConfigurationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_id',
        'blog_id'
    ];

    public function configuration(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NotificationConfiguration::class , 'config_id');
    }

    public function blog(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
