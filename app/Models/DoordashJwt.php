<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoordashJwt extends Model
{
    use HasFactory;

    protected $table = 'doordash_integration_jwts';

    public function doordashIntegrations(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DoordashIntegration::class, 'doordash_integration_id');
    }
}
