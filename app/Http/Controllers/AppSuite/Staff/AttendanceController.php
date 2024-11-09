<?php

namespace App\Http\Controllers\AppSuite\Staff;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\AttendanceAnalyticsService;
use App\Services\AttendanceService;
use App\Services\VenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;
    protected AttendanceAnalyticsService $analyticsService;
    protected VenueService $venueService;

    public function __construct(
        AttendanceService $attendanceService,
        AttendanceAnalyticsService $analyticsService,
        VenueService $venueService
    ) {
        $this->attendanceService = $attendanceService;
        $this->analyticsService = $analyticsService;
        $this->venueService = $venueService;
    }

    public function getAttendanceData(Request $request): JsonResponse
    {
        try {
            $employee = auth()->user()->employee;
            $date = $request->input('date') ? Carbon::parse($request->date) : null;

            $status = $this->attendanceService->getAttendanceStatus($employee, $date);

            return response()->json([
                'current_status' => [
                    'is_checked_in' => $status['is_checked_in'],
                    'last_record' => $status['last_record'],
                    'current_shift' => $status['current_shift'],
                    'can_check_in' => $status['can_check_in'],
                    'can_check_out' => $status['can_check_out']
                ]
            ]);

        } catch (\Exception $e) {
            dd($e);
            return response()->json(['error' => 'Failed to fetch attendance data'], 500);
        }
    }

    public function checkIn(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'scan_method' => 'required|in:nfc,qr',
                'nfc_card_id' => 'required_if:scan_method,nfc',
                'qr_code' => 'required_if:scan_method,qr',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 422);
            }



            $employee = $this->venueService->employee();

            $venue =  Restaurant::where('id', $employee->restaurant_id)->first();
            $result = $this->attendanceService->checkIn(
                $employee,
                $venue,
                $validator->validated()
            );

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], 422);
            }

            return response()->json([
                'message' => $result['message'],
                'record' => $result['record'],
                'warnings' => $result['warnings'] ?? []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check in'
            ], 500);
        }
    }

    public function checkOut(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'scan_method' => 'required|in:nfc,qr',
                'nfc_card_id' => 'required_if:scan_method,nfc',
                'qr_code' => 'required_if:scan_method,qr',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 422);
            }


            $employee = $this->venueService->employee();

            $venue =  Restaurant::where('id', $employee->restaurant_id)->first();
            $result = $this->attendanceService->checkOut(
                $employee,
                $venue,
                $validator->validated()
            );

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['message']
                ], 422);
            }

            return response()->json([
                'message' => $result['message'],
                'record' => $result['record'],
                'warnings' => $result['warnings'] ?? []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check out'
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $venue = $this->venueService->adminAuthCheck();
        if ($venue instanceof JsonResponse) return $venue;

        $timeFrame = $request->input('time_frame', 'monthly');

        return response()->json(
            $this->analyticsService->getAnalytics($venue->id, $timeFrame)
        );
    }

    public function export(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            $venue = $this->venueService->adminAuthCheck();
            if ($venue instanceof JsonResponse) return $venue;

            $timeFrame = $request->input('time_frame', 'monthly');

            return $this->analyticsService->exportAttendanceReport($venue->id, $timeFrame);

        } catch (\Exception $e) {
            \Log::error('Attendance export failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate export'], 500);
        }
    }
}
