<?php
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HouseKeepingTask;
use App\Models\RentalUnit;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use function response;

/**
 * @OA\Info(
 *   title="Housekeeping Task API",
 *   version="1.0",
 *   description="This API allows use Task Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Housekeeping Task",
 *   description="Operations related to Housekeeping Task"
 * )
 */


class HouseKeepingTaskController extends Controller
{

    public function createHouseKeepingTask(Request $request, $id = null): \Illuminate\Http\JsonResponse
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

        // rental units are allowed only for venue types vacation rental
        if (!($venue->venueType->short_name != 'vacation_rental' || $venue->venueType->short_name !== 'hotel')) {
            return response()->json(['error' => 'Venue type is not vacation rental'], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'nullable|string',
            'employee_id' => 'required|integer',
            'rental_unit_id' => 'required|integer',
            'due_date' => 'required|date_format:Y-m-d H:i:s'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $employee = Employee::find($request->input('employee_id'));
        if (!$employee) {
            return response()->json(['error' => 'Employee does not exist'], 400);
        }
        $rental_unit = RentalUnit::find($request->input('rental_unit_id'));
        if (!$rental_unit) {
            return response()->json(['error' => 'Rental Unit does not exist'], 400);
        }

        $data['name'] = $request->input('name');
        $data['description'] = $request->input('description');
        $data['due_date'] = $request->input('due_date');
        $data['employee_id'] = $request->input('employee_id');
        $data['rental_unit_id'] = $request->input('rental_unit_id');
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['venue_id'] = $venue->id;

        if ($id) {
            $task = HouseKeepingTask::find($id);
            if (!$task) {
                return response()->json(['error' => 'Task does not exist'], 400);
            }

            $task->name = $data['name'];
            $task->description = $data['description'];
            $task->due_date = $data['due_date'];
            $task->employee_id = $data['employee_id'];
            $task->rental_unit_id = $data['rental_unit_id'];
            $task->updated_at = $data['updated_at'];
            $task->venue_id = $data['venue_id'];
            $task->save();
            return response()->json(['message' => 'HouseKeeping Task updated successfully', 'task' => $task]);
        }
        else {
            $task = HouseKeepingTask::create($data);
            return response()->json(['message' => 'HouseKeeping Task created successfully', 'task' => $task]);
        }
    }

    public function getHouseKeepingTasks($id = null): \Illuminate\Http\JsonResponse
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

        // rental units are allowed only for venue types vacation rental
        if (!($venue->venueType->short_name != 'vacation_rental' || $venue->venueType->short_name !== 'hotel')) {
            return response()->json(['error' => 'Venue type is not vacation rental'], 400);
        }


        if ($id) {
            $task = HouseKeepingTask::where('id', $id)->where('venue_id', $venue->id)->first();
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }
            return response()->json([
                'data' => $task
            ]);
        }


        $tasks = HouseKeepingTask::where('venue_id', $venue->id)->get();

        foreach ($tasks as $index => $task) {
            $tasks[$index]->employee = $task->employee;
            $tasks[$index]->rental_unit = $task->rental_unit;
        }

        return response()->json([
            'data' => $tasks
        ]);
    }

    public function destroyTask($id): JsonResponse
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

        if (!($venue->venueType->short_name != 'vacation_rental' || $venue->venueType->short_name !== 'hotel')) {
            return response()->json(['error' => 'Venue type is not vacation rental'], 400);
        }

        $task = HouseKeepingTask::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$task) {
            return response()->json(['message' => 'The requested task does not exist'], 404);
        }

        $task->delete();

        return response()->json(['message' => 'Successfully deleted the task'], 200);
    }
}
