<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalFeeAndCharge extends Model
{
    use HasFactory;

    protected $fillable = ['venue_id', 'rental_unit_id', 'fee_name_id', 'amount'];

    public function feeName(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdditionalFeeAndChargesName::class, 'fee_name_id');
    }

    /**
     * Get the venue that owns the price.
     */
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function rental_unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }
}
