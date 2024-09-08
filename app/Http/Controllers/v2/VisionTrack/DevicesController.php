<?php
namespace App\Http\Controllers\v2\VisionTrack;
use App\Http\Controllers\Controller;
use App\Models\VtDevice;
use App\Models\VtStream;
use App\Services\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use function response;


class DevicesController extends Controller
{

    protected VenueService $venueService;
    protected string $visionTrackApiURL;

    public function __construct(VenueService $venueService)
    {
        $this->venueService = $venueService;
        // setup visiontrack api url
        $this->visionTrackApiURL =  env('VISION_TRACK_BASE_API_URL');
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:camera,other',
            'device_id' => 'nullable|string|unique:vt_devices,device_id',
            'device_nickname' => 'nullable|string',
            'location' => 'required|string',
            'brand' => 'required|in:UNV,Hikvision,Other',
            'setup_status' => 'nullable|in:configured,active,inactive,not configured',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deviceData = $request->all();

        // Generate device_id if not provided
        if (!isset($deviceData['device_id'])) {
            $deviceData['device_id'] = $this->generateUniqueDeviceId();
        }

        // Set default setup_status if not provided
        $deviceData['setup_status'] = $deviceData['setup_status'] ?? 'not configured';

        // Add venue_id to the device data
        $deviceData['venue_id'] = $venue->id;

        $device = VtDevice::create($deviceData);

        return response()->json(['message' => 'Device created successfully', 'device' => $device], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $devices = VtDevice::where('venue_id', $venue->id)
            ->when($request->has('type'), function ($query) use ($request) {
                return $query->where('type', $request->type);
            })
            ->when($request->has('brand'), function ($query) use ($request) {
                return $query->where('brand', $request->brand);
            })
            ->when($request->has('setup_status'), function ($query) use ($request) {
                return $query->where('setup_status', $request->setup_status);
            })
            ->paginate($request->per_page ?? 15);

        return response()->json($devices);
    }

    public function update(Request $request, $deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|in:camera,other',
            'device_nickname' => 'nullable|string',
            'location' => 'sometimes|required|string',
            'brand' => 'sometimes|required|in:UNV,Hikvision,Other',
            'setup_status' => 'sometimes|required|in:configured,active,inactive,not configured',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $device->update($request->all());

        return response()->json(['message' => 'Device updated successfully', 'device' => $device]);
    }

    public function delete($deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $device->delete();

        return response()->json(['message' => 'Device deleted successfully']);
    }

    private function generateUniqueDeviceId(): string
    {
        do {
            $deviceId = strtoupper(Str::random(8));
        } while (VtDevice::where('device_id', $deviceId)->exists());

        return $deviceId;
    }


    public function indexStreams($deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        //check if device exists

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);


        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $streams = $device->streams;

        return response()->json($streams);
    }

    public function AIPipelineStreams($deviceId): JsonResponse
    {

        //check if device exists

        $device = VtDevice::find($deviceId);

        $streams = $device->streams;

        return response()->json($streams);
    }

    public function storeStreams(Request $request, $deviceId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        //check if device exists

        $device = VtDevice::where('venue_id', $venue->id)
            ->find($deviceId);


        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $streamData = $request->all();
        $streamData['stream_id'] = $this->generateUniqueStreamId();
        $streamData['device_id'] = $device->id;

        $stream = VtStream::create($streamData);

        try {
            $response = Http::post($this->visionTrackApiURL . '/stream', [
                //'cameraId' => $deviceId,
                'streamId' => $stream->stream_id,
                'streamUrl' => $stream->url,
            ]);
            dd($response->json());
            // do nothing
        } catch (\Exception $e) {
            // do nothing
        }


        return response()->json(['message' => 'Stream created successfully', 'stream' => $stream], 201);
    }

    public function updateStreams(Request $request, $deviceId, $streamId): JsonResponse
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
            'name' => 'sometimes|required|string',
            'url' => 'sometimes|required|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $stream = VtStream::where('device_id', $device->id)
            ->where('id', $streamId)
            ->first();

        $stream->update($request->all());

        return response()->json(['message' => 'Stream updated successfully', 'stream' => $stream]);
    }

    public function deleteStreams($deviceId, $streamId): JsonResponse
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

        $stream = VtStream::where('device_id', $device->id)
            ->where('id', $streamId)
            ->first();

        $stream->delete();

        return response()->json(['message' => 'Stream deleted successfully']);
    }

    public function showStream($streamId): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();

        $stream = VtStream::where('stream_id', $streamId)
            //->where('venue_id', $venue->id)
            ->first();

        if (!$stream) {
            return response()->json(['message' => 'Stream not found'], 404);
        }

        $response = Http::get($this->visionTrackApiURL . '/stream', [
            //'cameraId' => $deviceId,
            // 'streamId' => $stream->stream_id
        ]);
        dd($response->json());

        return response()->json($stream);
    }

    private function generateUniqueStreamId(): string
    {
        do {
            $streamId = strtoupper(Str::random(8));
        } while (VtStream::where('stream_id', $streamId)->exists());

        return $streamId;
    }

}
