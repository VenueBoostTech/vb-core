<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\BowlingLane;
use App\Rules\NumericRangeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;


class BowlingLaneController extends Controller
{

    public function create(Request $request, $id = null): \Illuminate\Http\JsonResponse
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

        if ($venue->venueType->short_name != 'bowling') {
            return response()->json(['error' => 'Venue type is not bowling'], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'price_method' => 'required|string',
            'price' => ['required', 'numeric', new NumericRangeRule()],
            'max_allowed' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data['name'] = $request->input('name');
        $data['price_method'] = $request->input('price_method');
        $data['price'] = $request->input('price');
        $data['max_allowed'] = $request->input('max_allowed');
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['venue_id'] = $venue->id;

        try {
            if ($id) {
                $bowlingLane = BowlingLane::find($id);
                if (!$bowlingLane) {
                    return response()->json(['error' => 'Bowling Lane does not exist'], 400);
                }

                $bowlingLane->name = $data['name'];
                $bowlingLane->price_method = $data['price_method'];
                $bowlingLane->price = $data['price'];
                $bowlingLane->max_allowed = $data['max_allowed'];
                $bowlingLane->updated_at = $data['updated_at'];
                $bowlingLane->venue_id = $data['venue_id'];
                $bowlingLane->save();
                return response()->json(['message' => 'Bowling Lane updated successfully', 'data' => $bowlingLane]);
            }
            else {
                $bowlingLane = BowlingLane::create($data);
                return response()->json(['message' => 'Bowling Lane created successfully', 'data' => $bowlingLane]);
            }
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function get($id = null): \Illuminate\Http\JsonResponse
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

        if ($venue->venueType->short_name != 'bowling') {
            return response()->json(['error' => 'Venue type is not bowling'], 400);
        }

        if ($id) {
            $bowlingLane = BowlingLane::find($id);
            if (!$bowlingLane) {
                return response()->json(['error' => 'Bowling Lane not found'], 404);
            }
            return response()->json([
                'data' => $bowlingLane
            ]);
        }

        $lanes = BowlingLane::where('venue_id', $venue->id)->get();

        return response()->json([
            'data' => $lanes
        ]);
    }

    public function destroy($id): JsonResponse
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

        if ($venue->venueType->short_name != 'bowling') {
            return response()->json(['error' => 'Venue type is not bowling'], 400);
        }

        $bowlingLane = BowlingLane::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$bowlingLane) {
            return response()->json(['message' => 'The requested bowling lane does not exist'], 404);
        }

        $bowlingLane->delete();

        return response()->json(['message' => 'Successfully deleted the bowling lane'], 200);
    }
}
