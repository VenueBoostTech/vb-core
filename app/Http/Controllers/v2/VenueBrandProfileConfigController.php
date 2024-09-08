<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\IndustryBrandCustomizationElement;
use App\Models\VenueBrandProfileCustomization;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VenueBrandProfileConfigController extends Controller
{
    // Fetch all customizations
    private $venueService;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
    }

    public function get(): \Illuminate\Http\JsonResponse
    {
        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        try {
            $industryElements = IndustryBrandCustomizationElement::with(['venueBrandProfileCustomizations' => function ($query) use ($venue) {
                $query->where('venue_id', $venue->id);
            }])
                ->where('industry_id', $venue->venue_industry)
                ->get();

            return response()->json($industryElements);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request ): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'integer',
            'element_name' => 'string',
            'data' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
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
            VenueBrandProfileCustomization::where('element_id', $request->id)->where('venue_id', $venue->id)->delete();

            foreach ($request->data as $key => $value) {
                VenueBrandProfileCustomization::create([
                    'venue_id' => $venue->id,
                    'element_id' => $request->id,
                    'element_type' => $request->element_name == 'Footer' ? 'div' : 'button',
                    'customization_key' => $value['key'],
                    'customization_value' => $value['value'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            return response()->json([
                'message' => 'Customization updated successfully'
            ]);

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
