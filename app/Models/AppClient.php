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

    public function feedbacks()
    {
        return $this->hasMany(AppFeedback::class, 'client_id');
    }

    // Add support tickets relationship
    public function supportTickets(): HasMany
    {
        return $this->hasMany(AppSupportTicket::class, 'client_id');
    }

    // Add ticket messages relationship for messages sent by this client
    public function ticketMessages(): HasMany
    {
        return $this->hasMany(AppSupportTicketMessage::class, 'sender_id')
            ->where('sender_type', 'client');
    }

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class, 'client_id');
    }

    public function pendingServiceTickets(): HasMany
    {
        return $this->serviceTickets()
            ->whereIn('status', [
                ServiceTicket::STATUS_SCHEDULED,
                ServiceTicket::STATUS_IN_PROGRESS,
                ServiceTicket::STATUS_PENDING_SIGN_OFF
            ]);
    }

    public function completedServiceTickets(): HasMany
    {
        return $this->serviceTickets()
            ->where('status', ServiceTicket::STATUS_SIGNED_OFF);
    }

    public function getPendingSignOffsAttribute()
    {
        return $this->serviceTickets()
            ->where('status', ServiceTicket::STATUS_PENDING_SIGN_OFF)
            ->count();
    }

    public function getServiceHistoryAttribute()
    {
        return $this->serviceTickets()
            ->with(['service', 'photos'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function($ticket) {
                return $ticket->created_at->format('Y-m');
            });
    }

}
