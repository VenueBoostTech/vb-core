<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoordashIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'developer_id',
        'key_id',
        'signing_secret',
        'disconnected_at',
        'restaurant_id',
    ];

    public function jwts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DoordashJwt::class, 'doordash_integration_id');
    }
}
