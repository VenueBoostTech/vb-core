<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UberEatsIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'client_id',
        'client_secret',
        'disconnected_at',
        'restaurant_id',
    ];
}
