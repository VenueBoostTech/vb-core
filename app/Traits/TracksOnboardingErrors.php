<?php

namespace App\Traits;

use App\Models\OnboardingError;
use App\Models\PotentialVenueLead;
use Illuminate\Validation\ValidationException;

trait TracksOnboardingErrors
{
    protected function logOnboardingError($emailOrId, $step, $errorMessage, $stackTrace = null, $errorType = 'exception', $validationErrors = null)
    {
        $data = [
            'step' => $step,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'stack_trace' => $stackTrace,
            'validation_errors' => $validationErrors,
        ];

        if (filter_var($emailOrId, FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $emailOrId;
            $potentialVenueLead = PotentialVenueLead::where('email', $emailOrId)->first();
            if ($potentialVenueLead) {
                $data['potential_venue_lead_id'] = $potentialVenueLead->id;
            }
        } else {
            $data['potential_venue_lead_id'] = $emailOrId;
        }

        OnboardingError::create($data);
    }

    protected function logValidationError($emailOrId, $step, ValidationException $exception)
    {
        $this->logOnboardingError(
            $emailOrId,
            $step,
            'Validation failed',
            null,
            'validation',
            $exception->errors()
        );
    }
}
