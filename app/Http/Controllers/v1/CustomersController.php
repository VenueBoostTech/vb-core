<?php
namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use function event;
use function response;

/**
 * @OA\Info(
 *   title="Customers API",
 *   version="1.0",
 *   description="This API allows use Retail Related API for Venue Boost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Customers",
 *   description="Operations related to Customers"
 * )
 */


class CustomersController extends Controller
{
    public function getFoodDeliveryCustomers(): \Illuminate\Http\JsonResponse
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

        // Fetch all orders related to the venue
        // order by created_at latest first
        $orders = Order::where('restaurant_id', $venue->id)
            ->with('customer.customerAddresses.address')
            ->orderBy('created_at', 'desc')
            ->get();

        // Group orders by customer
        $groupedOrders = $orders->groupBy('customer_id');

        $result = [];

        foreach ($groupedOrders as $customerId => $customerOrders) {
            $firstOrder = $customerOrders->sortBy('created_at')->first();
            $customer = $firstOrder->customer;

            // Extract all addresses from customerAddresses relationship
            $addresses = $customer?->customerAddresses->map(function ($customerAddress) {
                return $customerAddress->address;
            });

            $result[] = [
                'name' => $customer?->name,
                'email' => $customer?->email,
                'phone' => $customer?->phone,
                'addresses' => $addresses,
                'first_order' => $firstOrder->created_at->format('F d, Y h:i A'),
                'total_orders' => $customerOrders->count()
            ];
        }

        return response()->json(['customers' => $result]);
    }


}
