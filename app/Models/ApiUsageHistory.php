<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiUsageHistory extends Model
{
    use HasFactory;

    protected $table = 'api_usage_history';
    protected $fillable = ['feature_id', 'sub_feature_id', 'note', 'venue_id'];

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function feature(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    public function subFeature(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SubFeature::class, 'sub_feature_id');
    }
}
