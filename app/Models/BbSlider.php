<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BbSlider extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bb_sliders';

    protected $fillable = [
        'venue_id',
        'bybest_id',
        'photo',
        'title',
        'url',
        'description',
        'button',
        'text_button',
        'slider_order',
        'status',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'button' => 'boolean',
        'status' => 'boolean',
        'slider_order' => 'integer',
    ];

    /**
     * Get the venue that owns the slider.
     */
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    /**
     * Scope a query to only include active sliders.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to order sliders by their order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('slider_order', 'asc');
    }


    /**
     * Check if the slider has a button.
     */
    public function hasButton()
    {
        return $this->button && $this->text_button;
    }
}
