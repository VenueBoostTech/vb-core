<?php

namespace App\Http\Controllers\v1\SNPlatform;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Info(
 *   title="SNReservations API",
 *   version="1.0",
 *   description="This API allows use SNReservations Related API for Venue Boost and Snapfood Platform"
 * )
 */

/**
 * @OA\Tag(
 *   name="SNReservations",
 *   description="Operations related to SNReservations"
 * )
 */
class SNReservationsController extends Controller
{
    /**
     * @OA\Post(
     *     path="/sn-platform-connect/reservations",
     *     operationId="createSNReservation",
     *     tags={"SNReservations"},
     *     summary="Create a new reservation",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="restaurant_id", type="integer"),
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="reserve_date", type="string"),
     *             @OA\Property(property="nr_of_guests", type="integer"),
     *             @OA\Property(property="notes", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Success"),
     *     @OA\Response(response="400", description="Bad Request")
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required',
            'customer_id' => 'required',
            'nr_of_guests' => 'required|integer',
            'reserve_date' => 'required|date|after_or_equal:start_time',
            'notes' => 'nullable|string'
        ]);


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Get an instance of the ReadAndWriteController
        $readAndWriteController = app(ReadAndWriteController::class);

        $isUnifiedEU = $readAndWriteController->rawEndUser($request->input('customer_id'));
        $isBoostRestaurant = $readAndWriteController->rRestaurant($request->input('restaurant_id'));

        // Check if the customer is unified
        if (!$isUnifiedEU) {
            return response()->json(['error' => 'Customer not found'], 400);
        }

        // Check if the restaurant is unified
        if (!$isBoostRestaurant) {
            return response()->json(['error' => 'Customer not found'], 400);
        }

        return response()->json(['message' => 'Reservation created successfully', 'data' => []], 201);

    }


}
