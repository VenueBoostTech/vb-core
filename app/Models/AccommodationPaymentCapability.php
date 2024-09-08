<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccommodationPaymentCapability extends Model
{
    use HasFactory;

    protected $table = 'accommodation_payment_capability';
    protected $fillable = ['can_charge_credit_cards', 'rental_unit_id', 'venue_id', 'accept_later_cash_payment'];

    public function rental_unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RentalUnit::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
