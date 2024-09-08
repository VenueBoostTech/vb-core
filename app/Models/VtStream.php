<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtStream extends Model
{
    use HasFactory;

    protected $fillable = ['stream_id', 'name', 'url', 'device_id'];

    public function device(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VtDevice::class, 'device_id');
    }
}
