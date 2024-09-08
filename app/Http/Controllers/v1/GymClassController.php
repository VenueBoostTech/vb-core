<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\GymClasses;
use App\Rules\NumericRangeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;


class GymClassController extends Controller
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

        if ($venue->venueType->short_name != 'gym') {
            return response()->json(['error' => 'Venue type is not gym'], 400);
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
                $gymclass = GymClasses::find($id);
                if (!$gymclass) {
                    return response()->json(['error' => 'Gym Class does not exist'], 400);
                }

                $gymclass->name = $data['name'];
                $gymclass->price_method = $data['price_method'];
                $gymclass->price = $data['price'];
                $gymclass->max_allowed = $data['max_allowed'];
                $gymclass->start_time = $data['start_time'];
                $gymclass->end_time = $data['end_time'];
                $gymclass->updated_at = $data['updated_at'];
                $gymclass->venue_id = $data['venue_id'];
                $gymclass->save();
                return response()->json(['message' => 'Gym Class updated successfully', 'data' => $gymclass]);
            }
            else {
                $gymclass = GymClasses::create($data);
                return response()->json(['message' => 'Gym Class created successfully', 'data' => $gymclass]);
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

        if ($venue->venueType->short_name != 'gym') {
            return response()->json(['error' => 'Venue type is not gym'], 400);
        }

        if ($id) {
            $gymclass = GymClasses::find($id);
            if (!$gymclass) {
                return response()->json(['error' => 'Gym Class not found'], 404);
            }
            return response()->json([
                'data' => $gymclass
            ]);
        }

        $gymclasses = GymClasses::where('venue_id', $venue->id)->get();

        return response()->json([
            'data' => $gymclasses
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

        if ($venue->venueType->short_name != 'gym') {
            return response()->json(['error' => 'Venue type is not gym'], 400);
        }

        $gymclass = GymClasses::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$gymclass) {
            return response()->json(['message' => 'The requested gym class does not exist'], 404);
        }

        $gymclass->delete();

        return response()->json(['message' => 'Successfully deleted the gym class'], 200);
    }
}
