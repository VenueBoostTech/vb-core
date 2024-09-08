<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableReservations extends Model
{
    use HasFactory;

    protected $table = 'table_reservations';
    protected $fillable = [
        'table_id', 'reservation_id', 'start_time', 'end_time'
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
