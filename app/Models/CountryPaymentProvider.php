<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryPaymentProvider extends Model
{
    use HasFactory;

    protected $table = 'country_payment_provider';

    protected $fillable = [
        'country_id',
        'payment_provider',
        'active',
        'start_time',
        'end_time',
    ];

    public function countries(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'country_payment_provider')
            ->withPivot('active', 'start_time', 'end_time');
    }

}
