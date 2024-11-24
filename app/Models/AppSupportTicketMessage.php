<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSupportTicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'message',
        'sender_type', // client, employee
        'sender_id',
        'attachments'
    ];

    protected $casts = [
        'attachments' => 'array'
    ];

    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AppSupportTicket::class);
    }

    public function sender()
    {
        return $this->sender_type === 'client'
            ? $this->belongsTo(AppClient::class, 'sender_id')
            : $this->belongsTo(Employee::class, 'sender_id');
    }
}
