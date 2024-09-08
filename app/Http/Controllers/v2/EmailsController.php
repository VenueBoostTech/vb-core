<?php

namespace App\Http\Controllers\v2;

use App\Enums\EmailType;
use App\Http\Controllers\Controller;
use App\Mail\Welcome12HEmail;
use App\Models\EmailConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class EmailsController extends Controller
{

    public static function sendWelcome12HEmail($venue){

        Mail::to($venue->email)->send(new Welcome12HEmail($venue->venueType->definition));
        $emailConfig = EmailConfiguration::where('type', EmailType::WELCOME_12_H)->first();
        // Attach the email configuration to the restaurant
        $venue->emailConfigurations()->attach($emailConfig->id);
    }

    public static function getKlaviyoTemplates(): \Illuminate\Http\JsonResponse
    {

        if (!auth()->user()->restaurants->count()) {
            return response()->json(['error' => 'User not eligible for making this API call'], 400);
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $apiKey = env('KLAVIYO_API_KEY');
        $klaviyoApiUrl = env('KLAVIYO_API_URL');

        $response = Http::withHeaders([
            'accept' => 'application/json',
        ])->get("{$klaviyoApiUrl}/email-templates?page=0&count=50&api_key={$apiKey}");

        return response()->json(['templates' => $response->json()['data']], 200);

    }
}
