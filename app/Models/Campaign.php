<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'promotion_id',
        'title',
        'description',
        'link',
        'type',
        'target',
        'scheduled_date',
        'sent',
        'external_ids'
    ];


    protected $casts = [
        'scheduled_date' => 'datetime',
        'sent' => 'boolean',
        'external_ids' => 'json',
    ];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function promotion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Get all campaigns for OmniStack integration
     *
     * @param int $venueId
     * @return array
     */
    public static function getCampaignsForOmniStack($venueId)
    {
        $campaigns = self::with(['promotion'])
            ->where('venue_id', $venueId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'description' => $campaign->description,
                'link' => $campaign->link,
                'type' => $campaign->type,
                'target' => $campaign->target,
                'scheduled_date' => $campaign->scheduled_date ? $campaign->scheduled_date->format('Y-m-d H:i:s') : null,
                'sent' => (bool) $campaign->sent,
                'promotion_id' => $campaign->promotion_id,
                'promotion' => $campaign->promotion ? [
                    'id' => $campaign->promotion->id,
                    'name' => $campaign->promotion->name,
                    'description' => $campaign->promotion->description,
                    'type' => $campaign->promotion->type,
                ] : null,
                'created_at' => $campaign->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $campaign->updated_at->format('Y-m-d H:i:s')
            ];
        })->toArray();
    }
}
