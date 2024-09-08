<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WcIntegration extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'wc_integrations';

    protected $fillable = [
        'consumer_key',
        'consumer_secret',
        'consumer_wc_website',
        'venue_id',
    ];

    protected $dates = ['deleted_at'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
