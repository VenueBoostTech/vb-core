<?php

namespace App\Services;

use App\Models\ApiUsageHistory;

class ApiUsageLogger
{
    public static function log($featureId, $venueId, $note, $subFeatureId = null)
    {
        ApiUsageHistory::create([
            'feature_id' => $featureId,
            'venue_id' => $venueId,
            'note' => $note,
            'sub_feature_id' => $subFeatureId,
        ]);
    }
}
