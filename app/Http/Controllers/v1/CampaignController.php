<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Mail\CampaignEmail;
use App\Mail\InPlaceNotificationEmail;
use App\Models\AutomaticReply;
use App\Models\Customer;
use App\Models\Guest;
use App\Models\Promotion;
use App\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JetBrains\PhpStorm\NoReturn;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;
use function response;

class CampaignController extends Controller
{
    public function index(): JsonResponse
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

        $campaigns = Campaign::where('venue_id', $venue->id)->get();
        // Load the associated discounts for each promotion
        $campaigns->load('promotion');

        return response()->json(['message' => 'Campaigns retrieved successfully', 'data' => $campaigns], 200);
    }

    public function store(Request $request): JsonResponse
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

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|in:SMS,Email',
            'promotion_id' => 'nullable|exists:promotions,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $promotionId = $request->input('promotion_id');
        $promotion = null;

        if (!$promotionId && ! $request->input('target')) {
            return response()->json(['error' => 'Target is required'], 400);
        }

        if ($promotionId) {
            $promotion = Promotion::where('id', $promotionId)
                ->where('status', true)
                ->where(function ($query) {
                    $query->where('end_time', '>=', now());
                })
                ->first();

            if (!$promotion) {
                return response()->json(['error' => 'Invalid or inactive promotion provided'], 400);
            }
        }

        $scheduledDate = $request->input('scheduled_date', Carbon::now());

        $campaign = Campaign::create([
            'venue_id' => $venue->id,
            'promotion_id' => $promotionId ?? null,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'link' => $request->input('link'),
            'type' => $request->input('type'),
            'target' => $promotionId ?  null: $request->input('target'),
            'scheduled_date' => $scheduledDate
        ]);

        if (!$request->input('scheduled_date')) {

            // Twilio account information
            $account_sid = env('TWILIO_ACCOUNT_SID');
            $auth_token = env('TWILIO_AUTH_TOKEN');
            $twilio_number = env('TWILIO_NUMBER');

            $client = new Client($account_sid, $auth_token);

            if ($venue->venueType->definition === 'retail') {
                $guests = Customer::where([
                    ['venue_id', $venue->id],
                ])->get();

            } else {
                $guests = Guest::where([
                    ['is_for_retail', true],
                    ['restaurant_id', $venue->id],
                ])->get();
            }

            if ($request->input('type') === 'SMS') {
                foreach ($guests as $guest) {
                    if ($guest->phone) {
                        try {
                            // Send SMS message
                            $client->messages->create(
                                $guest->phone,
                                array(
                                    'from' => $twilio_number,
                                    'body' => $request->input('description')
                                )
                            );
                        } catch (\Exception $e) {
                            // do nothing
                            \Sentry\captureException($e);
                        }
                    }
                }
            } else {
                foreach ($guests as $guest) {
                    if ($guest->email) {
                        try {
                            $venueLogo = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+8000 minutes') : null;
                            // Send email
                            $restaurantName = $venue->name;
                            $subject = $request->input('title');
                            $content = $request->input('description');
                            Mail::to($guest->email)->send(new CampaignEmail($subject, $content, $restaurantName, $venueLogo));
                        } catch (\Exception $e) {
                            // do nothing
                            \Sentry\captureException($e);
                        }
                    }
                }
            }

            $campaign->update(['sent' => true]);
        }

       // TODO add cronjob to send campaigns at scheduled time

        return response()->json(['message' => 'Campaign added successfully', 'campaign' => $campaign], 201);
    }

    public function update(Request $request): JsonResponse
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
        $campaign = Campaign::where('id', $request->id)
        ->where('venue_id', $venue->id)
        ->first();
        

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        if ($campaign->sent) {
            return response()->json(['error' => 'Campaign has already been sent and cannot be updated'], 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|in:SMS,Email',
            'scheduled_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $promotionId = $request->input('promotion_id');

        // if (!$promotionId && ! $request->input('target')) {
        //     return response()->json(['error' => 'Target is required'], 400);
        // }

        if ($promotionId) {

            if ($request->promotion_id != $campaign->promotion_id) {
                $newPromotion = Promotion::where('id', $request->promotion_id)->where('venue_id', $venue->id)->first();

                if (!$newPromotion) {
                    return response()->json(['error' => 'Promotion not found'], 404);
                }
            }
        }

        // Update the promotion attributes
        $campaign->promotion_id = $promotionId;
        $campaign->title = $request->title;
        $campaign->description = $request->description;
        $campaign->link = $request->link ?? null;
        $campaign->type = $request->type;
        $campaign->target = $promotionId ? null: $request->input('target');
        $campaign->scheduled_date = $request->scheduled_date;
        $campaign->save();

        // Load the associated discount
        $campaign->load('promotion');

        return response()->json(['message' => 'Campaign updated successfully', 'data' => $campaign], 200);
    }

    public function delete($id): \Illuminate\Http\JsonResponse
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

        try {
            $campaign = Campaign::where('id', $id)
                ->where('venue_id', $venue->id)
                ->first();

            if (!$campaign) {
                return response()->json(['message' => 'Not found campaign'], 404);
            }
            $campaign->delete();
            return response()->json(['message' => 'Campaign is deleted successfully'], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @throws ConfigurationException
     * @throws TwilioException
     */
    #[NoReturn] public static function sendNotification($campaign){

        // Twilio account information
        $account_sid = env('TWILIO_ACCOUNT_SID');
        $auth_token = env('TWILIO_AUTH_TOKEN');
        $twilio_number = env('TWILIO_NUMBER');

        $client = new Client($account_sid, $auth_token);
        $venue = $campaign->venue;
        $type = $campaign->type;
        $description = $campaign->description;

        $guests = Guest::where([
            ['is_main', true],
            ['restaurant_id', $venue->id],
        ])->get();

        foreach ($guests as $guest) {
            if ($type === 'SMS' && $guest->phone) {
                $client->messages->create(
                    $guest->phone,
                    array(
                        'from' => $twilio_number,
                        'body' => $description
                    )
                );
            } elseif ($type === 'Email' && $guest->email) {
                $restaurantName = $venue->name;
                $subject = $campaign->title;
                Mail::to($guest->email)->send(new CampaignEmail($subject, $description, $restaurantName));
            }
        }

        // Update the campaign sent
        $campaign->update(['sent' => 1]);



    }


}
