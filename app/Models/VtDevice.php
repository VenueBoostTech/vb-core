<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtDevice extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vt_devices';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'device_id',
        'device_nickname',
        'location',
        'brand',
        'setup_status',
        'venue_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type' => 'string',
        'brand' => 'string',
        'setup_status' => 'string',
    ];

    /**
     * Get the venue that owns the device.
     */
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function streams(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VtStream::class, 'device_id');
    }
}
