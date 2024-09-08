<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_id',
        'start_time',
        'end_time',
        'seating_arrangement',
        'guest_count',
        'notes',
        'occation',
        'confirmed',
        'insertion_type',
        'source',
        'restaurant_id',
        'hotel_restaurant_id',
        'hotel_gym_id',
        'hotel_events_hall_id',
    ];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function guests()
    {
        return $this->belongsToMany('App\Models\Guest');
    }

    public function waitlist()
    {
        return $this->hasOne(Waitlist::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function table_reservation()
    {
        return $this->hasOne(TableReservations::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function earn_points_history(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EarnPointsHistory::class);
    }

    public function use_points_history(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UsePointsHistory::class);
    }

    public function promotion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

}
