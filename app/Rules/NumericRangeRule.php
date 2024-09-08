<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class NumericRangeRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Define the maximum allowed value for the column (e.g., 999999.99 for 8,2 decimal)
        $maxValue = 999999.99;

        // Define the minimum allowed value (usually 0 for positive decimals)
        $minValue = 0;

        // Check if the value is numeric and within the allowed range
        return is_numeric($value) && $value >= $minValue && $value <= $maxValue;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be a number within the allowed range.';
    }
}
