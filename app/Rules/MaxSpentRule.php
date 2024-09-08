<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class MaxSpentRule implements Rule
{
    private $minimumSpent;

    public function __construct($minimumSpent)
    {
        $this->minimumSpent = $minimumSpent;
    }

    public function passes($attribute, $value)
    {
        if (is_null($value)) {
            return true;  // because it's nullable
        }

        return $value >= $this->minimumSpent;
    }

    public function message()
    {
        return 'The :attribute must be greater than or equal to minimum spent.';
    }
}
