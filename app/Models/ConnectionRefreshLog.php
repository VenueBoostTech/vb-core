<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectionRefreshLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',     // ID of the related connection
        'connection_type',   // Type of connection (e.g., Airbnb, Booking)
        'status',            // Status of the refresh attempt (success or error)
        'message',           // Detailed log message
        'venue_id',
        'rental_unit_id'
    ];

    /**
     * Get the connection associated with the refresh log.
     */
    public function connection(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'connection_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function rentalUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }
}
