<?php

namespace App\Services;
use App\Mail\NewLeadNotification;


use Illuminate\Support\Facades\Mail;

class TMActivePiecesAutomationsService
{

    public function automateLeadCreation($potentialVenueLead, $subscribe): bool
    {

        // Define the GraphQL mutation
        $itemName = $potentialVenueLead->representative_first_name .' ' . $potentialVenueLead->representative_last_name;
        $itemEmail = $potentialVenueLead->email;


        // Send email notification
        try {
            Mail::to('development@venueboost.io')->send(new NewLeadNotification($itemName, $itemEmail, 'Griseld'));
            //Mail::to('rf@venueboost.io')->send(new NewLeadNotification($itemName, $itemEmail, 'Redi'));
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            \Log::error('Failed to send new lead notification email: ' . $e->getMessage());
        }


        return true;

    }

}
