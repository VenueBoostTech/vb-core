<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'category_id',
        'name',
        'price_type',
        'base_price',
        'duration',
        'description',
        'status'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'duration' => 'integer'
    ];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    // Add this relationship
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(AppSupportTicket::class);
    }
}
