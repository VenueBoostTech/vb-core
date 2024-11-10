<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppClient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'contact_person',
        'email',
        'phone',
        'address_id',
        'venue_id',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'type' => 'string',
    ];

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(AppProject::class, 'client_id');
    }

    // Helper method to check if client has associated user account
    public function hasUserAccount(): bool
    {
        return !is_null($this->user_id);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'client_id');
    }

    // Helper method to get active service requests
    public function activeServiceRequests(): HasMany
    {
        return $this->serviceRequests()
            ->whereNotIn('status', ['Completed', 'Cancelled']);
    }

    // Helper method to get active projects
    public function activeProjects(): HasMany
    {
        return $this->projects()
            ->whereNotIn('status', [
                AppProject::STATUS_COMPLETED,
                AppProject::STATUS_CANCELLED
            ]);
    }

    // Add relation to User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
