<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\GolfCourseTypes;
use App\Rules\NumericRangeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;


class GolfCourseTypesController extends Controller
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

        if ($venue->venueType->short_name != 'golf_venue') {
            return response()->json(['error' => 'Venue type is not golf'], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'price_method' => 'required|string',
            'price' => ['required', 'numeric', new NumericRangeRule()],
            'max_allowed' => 'required|integer',
            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'required|date_format:Y-m-d H:i:s|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data['name'] = $request->input('name');
        $data['price_method'] = $request->input('price_method');
        $data['price'] = $request->input('price');
        $data['max_allowed'] = $request->input('max_allowed');
        $data['start_time'] = $request->input('start_time');
        $data['end_time'] = $request->input('end_time');
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['venue_id'] = $venue->id;

        try {
            if ($id) {
                $golfct = GolfCourseTypes::find($id);
                if (!$golfct) {
                    return response()->json(['error' => 'Golf Course Type does not exist'], 400);
                }

                $golfct->name = $data['name'];
                $golfct->price_method = $data['price_method'];
                $golfct->price = $data['price'];
                $golfct->max_allowed = $data['max_allowed'];
                $golfct->start_time = $data['start_time'];
                $golfct->end_time = $data['end_time'];
                $golfct->updated_at = $data['updated_at'];
                $golfct->venue_id = $data['venue_id'];
                $golfct->save();
                return response()->json(['message' => 'Golf Course Type updated successfully', 'data' => $golfct]);
            }
            else {
                $golfct = GolfCourseTypes::create($data);
                return response()->json(['message' => 'Golf Course Type created successfully', 'data' => $golfct]);
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

        if ($venue->venueType->short_name != 'golf_venue') {
            return response()->json(['error' => 'Venue type is not golf'], 400);
        }

        if ($id) {
            $golfct = GolfCourseTypes::find($id);
            if (!$golfct) {
                return response()->json(['error' => 'Golf Course Type not found'], 404);
            }
            return response()->json([
                'data' => $golfct
            ]);
        }

        $GolfCourseTypes = GolfCourseTypes::where('venue_id', $venue->id)->get();

        return response()->json([
            'data' => $GolfCourseTypes
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

        if ($venue->venueType->short_name != 'golf_venue') {
            return response()->json(['error' => 'Venue type is not golf'], 400);
        }

        $golfct = GolfCourseTypes::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$golfct) {
            return response()->json(['message' => 'The requested Golf Course Type does not exist'], 404);
        }

        $golfct->delete();

        return response()->json(['message' => 'Successfully deleted the Golf Course Type'], 200);
    }
}
