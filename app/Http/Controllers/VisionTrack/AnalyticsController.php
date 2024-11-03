<?php
namespace App\Http\Controllers\VisionTrack;
use App\Http\Controllers\Controller;
use App\Models\VtDevice;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use function response;


class AnalyticsController extends Controller
{

    protected VenueService $venueService;
    protected string $visionTrackApiURL;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;

        // setup visiontrack api url
        $this->visionTrackApiURL =  env('VISION_TRACK_BASE_API_URL');
    }

    public function peopleCount(Request $request, $deviceId, $streamId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $stream = $device->streams->pluck('id');

        //check if stream exists
        if (!$stream->contains($streamId)) {
            return response()->json(['message' => 'Stream not found'], 404);
        }

        $validator = Validator::make($request->all(), [
           'start_time' => 'required|date',
              'end_time' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');

        try {
            $response = Http::get( $this->visionTrackApiURL . '/people-count', [
                'start_time' => $start_time,
                'end_time' => $end_time,
                'camera_id' => $deviceId,
            ]);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error communicating with server. Try again in a bit.'], 500);
        }

    }

    public function demographics(Request $request, $deviceId, $streamId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $stream = $device->streams->pluck('id');

        //check if stream exists
        if (!$stream->contains($streamId)) {
            return response()->json(['message' => 'Stream not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');

        try {
            $response = Http::get( $this->visionTrackApiURL . '/demographics', [
                'start_time' => $start_time,
                'end_time' => $end_time,
                'camera_id' => $deviceId,
            ]);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error communicating with server. Try again in a bit.'], 500);
        }

    }

    public function trafficTrends(Request $request, $deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time' => 'required|date',
            'interval' => 'required|in:hourly,daily,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');

        try {
            $response = Http::get( $this->visionTrackApiURL . '/traffic-trends', [
                'start_time' => $start_time,
                'end_time' => $end_time,
                'camera_id' => $deviceId,
                'interval' => $request->input('interval'),
            ]);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error communicating with server. Try again in a bit.'], 500);
        }

    }

    public function demographicTrafficOverview(Request $request, $deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time' => 'required|date',
            'interval' => 'required|in:hourly,daily,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');

        try {
            $response = Http::get( $this->visionTrackApiURL . '/demographic-traffic-overview', [
                'start_time' => $start_time,
                'end_time' => $end_time,
                'interval' => $request->input('interval'),
                'camera_id' => $deviceId,
            ]);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error communicating with server. Try again in a bit.'], 500);
        }

    }

    public function setEntryLog(Request $request, $deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'timestamp' => 'required|date',
            'personId' => 'required',
            'gender' => 'required',
            'age' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $timestamp = $request->input('timestamp');
        $personId = $request->input('personId');
        $gender = $request->input('gender');
        $age = $request->input('age');
        try {
            $response = Http::get( $this->visionTrackApiURL . '/entrylog', [
                'timestamp' => $timestamp,
                'personId' => $personId,
                'gender' => $gender,
                'age' => $age,
                'cameraId' => $deviceId,
            ]);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error communicating with server. Try again in a bit.'], 500);
        }

    }

    public function setExitLog(Request $request, $deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'timestamp' => 'required|date',
            'personId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $timestamp = $request->input('timestamp');
        $personId = $request->input('personId');
        try {
            $response = Http::get( $this->visionTrackApiURL . '/exitlog', [
                'timestamp' => $timestamp,
                'personId' => $personId,
                'cameraId' => $deviceId,
            ]);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error communicating with server. Try again in a bit.'], 500);
        }

    }

}
