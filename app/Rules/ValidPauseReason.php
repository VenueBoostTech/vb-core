<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidPauseReason implements Rule
{
    protected array $validReasons = [
        'Going on vacation',
        'Not yet ready to go live',
        'Booking season has ended',
        'Calendar fully booked',
        'Natural disaster in area',
        'Venue Undergoing maintenance',
        'Not yet ready to take bookings/accept reservations'
    ];

    public function passes($attribute, $value): bool
    {
        return in_array($value, $this->validReasons);
    }

    public function message(): string
    {
        return 'The selected reason is invalid.';
    }
}
