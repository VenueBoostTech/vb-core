<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Performance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use function response;

/**
 * @OA\Info(
 *   title="Staff Management API",
 *   version="1.0",
 *   description="This API allows use Staff Management Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Staff Management",
 *   description="Operations related to Staff Management"
 * )
 */

class PerformanceController extends Controller
{

    /**
     * @OA\Post(
     *     path="/staff/performance",
     *     operationId="createPerformance",
     *     tags={"Staff Management"},
     *     summary="Create a new performance record for staff employee",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="attendance", type="integer"),
     *             @OA\Property(property="punctuality", type="integer"),
     *             @OA\Property(property="productivity", type="integer"),
     *             @OA\Property(property="performance_date", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Success"),
     *     @OA\Response(response="422", description="Validation Error")
     * )
     */
    public function create(Request $request): \Illuminate\Http\JsonResponse
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

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer',
            'attendance' => 'required|integer',
            'punctuality' => 'required|integer',
            'productivity' => 'required|integer',
            'performance_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $employeeId = $request->employee_id;
        $employee = Employee::where('id', $employeeId)->where('restaurant_id', $venue->id)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 400);
        }

        // Create a new performance record
        $performance = new Performance;
        $performance->employee_id = $request->input('employee_id');
        $performance->attendance = $request->input('attendance');
        $performance->punctuality = $request->input('punctuality');
        $performance->productivity = $request->input('productivity');
        $performance->performance_date = $request->input('performance_date');
        $performance->restaurant_id = $venue->id;
        $performance->save();

        return response()->json([
            'message' => 'Performance record created',
            'performance' => $performance,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/staff/performance/{id}",
     *     summary="Get performance by Employee ID",
     *     tags={"Staff Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the employee",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid ID"
     *     )
     * )
     */
    public function getPerformanceByEmployee($id): \Illuminate\Http\JsonResponse
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

            $employee = Employee::where('id', $id)->where('restaurant_id', $venue->id)->first();

            if (!$employee) {
                return response()->json(['error' => 'Employee not found'], 400);
            }

            $performances = Performance::with('employee')->where('employee_id', $id)->get();
            return response()->json(['performances' => $performances]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid ID'], 422);
        }
    }

    /**
     * @OA\Get(
     *      path="/staff/performance",
     *      operationId="listPerformance",
     *      tags={"Staff Management"},
     *      summary="List all performances",
     *      description="Returns a list of all performances",
     *      @OA\Response(response=200, description="successful operation"),
     *      @OA\Response(response=401, description="unauthorized"),
     *      @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
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
        $performances = Performance::with('employee')->where('restaurant_id', $venue->id)->get();
        return response()->json(['data' => $performances], 200);
    }

}
