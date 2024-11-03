<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndUserAddress extends Model
{
    use HasFactory;

    //users // addressess //customer_addresses

    protected $fillable = [
        'user_id',
        'address_id',
        'customer_address_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function customer_address()
    {
        return $this->belongsTo(CustomerAddress::class);
    }
}
