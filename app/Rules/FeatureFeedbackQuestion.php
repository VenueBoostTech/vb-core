<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class FeatureFeedbackQuestion implements Rule
{

    protected $featureName;

    public function __construct($featureName)
    {
        $this->featureName = $featureName;
    }

    public function passes($attribute, $value): bool
    {
        // Define the valid questions
        $validQuestions = [
            "Is this page useful in understanding what the feature {$this->featureName} does?",
            "Is anything unclear on this feature or do you have any questions about {$this->featureName}?",
            "Is there any additional feedback that you would like to improve feature: {$this->featureName}?",
        ];

        // Check if the value is in the list of valid questions
        return in_array($value, $validQuestions);
    }

    public function message(): string
    {
        return 'The :attribute must be one of the valid questions.';
    }
}
