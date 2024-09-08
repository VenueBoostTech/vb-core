<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Expense;
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

class ExpensesController extends Controller
{

    /**
     * @OA\Get(
     *      path="/staff/expense",
     *      operationId="listExpenses",
     *      tags={"Staff Management"},
     *      summary="List all expenses",
     *      description="Returns a list of all expenses",
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

        $expenses = Expense::with('employee')->where('restaurant_id', $venue->id)->get();
        return response()->json(['data' => $expenses], 200);
    }

    /**
     * @OA\Post(
     *     path="/staff/expense",
     *     operationId="createExpense",
     *     tags={"Staff Management"},
     *     summary="Create a new expense",
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="employee_id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="amount",
     *                     type="number"
     *                 ),
     *                 @OA\Property(
     *                     property="date",
     *                     type="string",
     *                     format="date"
     *                 ),
     *                 example={
     *                      "employee_id": 1,
     *                      "type": "Mileage",
     *                      "amount": "50",
     *                      "date": "2022-01-01"
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */

    public function createExpense(Request $request): \Illuminate\Http\JsonResponse
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

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer',
            'type' => 'required|string',
            'amount' => 'required|numeric',
            'date' => 'required|date'

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $employeeId = $request->employee_id;
        $employee = Employee::where('id', $employeeId)->where('restaurant_id', $venue->id)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 400);
        }

        // Create a new expense
        $expense = new Expense();
        $expense->employee_id = $employeeId;
        $expense->type = $request->type;
        $expense->amount = $request->amount;
        $expense->date = $request->date;
        $expense->restaurant_id = $venue->id;
        $expense->save();

        return response()->json([
            'message' => 'Expense created successfully',
            'expense' => $expense,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/staff/expenses/{id}",
     *     summary="Get expense by Employee ID",
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
    public function getExpenseByEmployee($id): \Illuminate\Http\JsonResponse
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

            $expenses = Expense::with('employee')->where('employee_id', $id)->get();
            return response()->json(['expenses' => $expenses]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => 'Invalid ID'], 422);
        }
    }
}
