<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'customer_id',
        'address_id',
    ];

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Address::class);
    }
}
