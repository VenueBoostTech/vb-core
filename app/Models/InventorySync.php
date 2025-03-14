<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventorySync extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'total_pages',
        'processed_pages',
        'batch_id',
        'started_at',
        'completed_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the venues associated with this sync
     */
    public function venues()
    {
        return $this->belongsToMany(Restaurant::class, 'inventory_sync_venue', 'inventory_sync_id', 'venue_id')
            ->withPivot('last_sync_at', 'status')
            ->withTimestamps();
    }

    /**
     * Increment the processed pages counter
     */
    public function incrementProcessedPages($count = 1)
    {
        $this->processed_pages += $count;

        // Update progress percentage
        if ($this->total_pages > 0) {
            $this->progress_percentage = ($this->processed_pages / $this->total_pages) * 100;
        }

        return $this->save();
    }
}
