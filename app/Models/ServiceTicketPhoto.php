<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTicketPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['service_ticket_id', 'photo_path', 'photo_type', 'description', 'taken_by', 'taken_at', 'metadata'];

    public function serviceTicket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class);
    }
}
