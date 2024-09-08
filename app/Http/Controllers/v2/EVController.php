<?php
namespace App\Http\Controllers\v2;
use App\Http\Controllers\Controller;

use App\Models\VenueBeachArea;
use App\Models\VenueBeachBarConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use stdClass;
use function event;
use function response;

/**
 * @OA\Info(
 *   title="Entertainment Venues API",
 *   version="1.0",
 *   description="This API allows use Entertainment Venues Related API for VenueBoost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Entertainment Venues",
 *   description="Operations related to Entertainment Venues"
 * )
 */


class EVController extends Controller
{
    public function cuBeachBarConfiguration(Request $request): \Illuminate\Http\JsonResponse
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

        // beach bar configuration is allowed only for beach bar venue types
        if ($venue->venueType->short_name != 'beach_bar') {
            return response()->json(['error' => 'Venue type is not beach bar'], 400);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'currency' => [
                'nullable',
                Rule::in(['ALL', 'USD', 'EUR'])
            ],
            'has_beach_menu' => 'nullable | boolean',
            'has_restaurant_menu' => 'nullable | boolean',
            'default_umbrellas_check_in' => 'required|date_format:H:i',
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $configuration = VenueBeachBarConfiguration::updateOrCreate(
            ['venue_id' => $venue->id],
            $request->only('has_restaurant_menu', 'has_beach_menu', 'default_umbrellas_check_in', 'currency')
        );

        return response()->json(['message' => 'Configuration created/updated successfully', 'configuration' => $configuration]);

    }


    public function getBeachBarConfiguration(Request $request): \Illuminate\Http\JsonResponse
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

        // beach bar configuration is allowed only for beach bar venue types
        if ($venue->venueType->short_name != 'beach_bar') {
            return response()->json(['error' => 'Venue type is not beach bar'], 400);
        }

        $configuration = VenueBeachBarConfiguration::where('venue_id', $venue->id)->first();


        return response()->json(['configuration' => $configuration ?? new stdClass(), 'message' => 'Configuration retrieved successfully']);

    }

    public function createArea(Request $request): \Illuminate\Http\JsonResponse
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

        // beach bar configuration is allowed only for beach bar venue types
        if ($venue->venueType->short_name != 'beach_bar') {
            return response()->json(['error' => 'Venue type is not beach bar'], 400);
        }

        $validator = Validator::make($request->all(), [
            'unique_code' => [
                'required',
                'string',
                'min:1',
                'max:3',
                Rule::unique('venue_beach_areas')->where(function ($query) use ($request, $venue) {
                    return $query->where('venue_id', $venue->id);
                }),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $venueBeachArea = VenueBeachArea::create([
            'unique_code' => $request['unique_code'],
            'venue_id' => $venue->id
        ]);



        return response()->json([
            'message' => 'Area created successfully',
            'data' => $venueBeachArea
        ]);
    }

    public function editArea(Request $request): \Illuminate\Http\JsonResponse
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

        // beach bar configuration is allowed only for beach bar venue types
        if ($venue->venueType->short_name != 'beach_bar') {
            return response()->json(['error' => 'Venue type is not beach bar'], 400);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:venue_beach_areas,id',
            'unique_code' => [
                'required',
                'string',
                'min:1',
                'max:3',
                Rule::unique('venue_beach_areas')->where(function ($query) use ($request) {
                    return $query->where('venue_id', $request->venue_id);
                })->ignore($request['id']),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $area = VenueBeachArea::where('id', $request['id'])->where('venue_id', $venue->id)->first();
        if (!$area) {
            return response()->json(['error' => 'Area not found'], 404);
        }

        $area->update([
            'unique_code' => $request['unique_code']
        ]);

        return response()->json([
            'message' => 'Area updated successfully',
            'data' => $area
        ]);
    }

    public function deleteArea($id): \Illuminate\Http\JsonResponse
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

        // beach bar configuration is allowed only for beach bar venue types
        if ($venue->venueType->short_name != 'beach_bar') {
            return response()->json(['error' => 'Venue type is not beach bar'], 400);
        }


        $area = VenueBeachArea::where('id', $id)->where('venue_id', $venue->id)->first();


        if (!$area) {
            return response()->json(['error' => 'Area not found'], 404);
        }

        $area->delete();


        return response()->json(['message' => 'Beach bar deleted successfully']);
    }

    public function listAreas(Request $request): \Illuminate\Http\JsonResponse
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


        // beach bar configuration is allowed only for beach bar venue types
        if ($venue->venueType->short_name != 'beach_bar') {
            return response()->json(['error' => 'Venue type is not beach bar'], 400);
        }


        $areas = VenueBeachArea::where('venue_id', $venue->id)
            ->orderBy('id', 'desc')
            ->get();


        return response()->json([
            'message' => 'Areas retrieved successfully',
            'data' => $areas
        ]);
    }


}
