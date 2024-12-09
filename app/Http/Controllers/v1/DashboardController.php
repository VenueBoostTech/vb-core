<?php

namespace App\Http\Controllers\v1;
use App\Enums\InventoryActivityCategory;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\RentalUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;
use function response;

/**
 * @OA\Info(
 *   title="Dashboard API",
 *   version="1.0",
 *   description="This API allows use Dashboard (+ Analytics + Customer Insights) Related API for VenueBoost"
 * )
 */

/**
 * @OA\Tag(
 *   name="Dashboard",
 *   description="Operations related to Dashboard"
 * )
 */
class DashboardController extends Controller
{
    public function weeklyReservations(Request $request): \Illuminate\Http\JsonResponse
    {
        $response = [];

        return response()->json($response);
    }

    public function tableTurnaroundTime(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function averageTableOccupancy(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function reservationSourceAnalysis(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function guestAttendance(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function popularItems(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function topGuests(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];
        return response()->json(['data' => $data]);
    }

    public function guestsOverview(Request $request): \Illuminate\Http\JsonResponse
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

        $dates = $this->getDateRange($request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // Top 5 guests
        $topGuests = Guest::select('guests.*', DB::raw('COUNT(bookings.id) as booking_count'))
            ->join('bookings', 'guests.id', '=', 'bookings.guest_id')
            ->where('bookings.venue_id', $venue->id)
            ->whereBetween('bookings.check_in_date', [$startDate, $endDate])
            ->groupBy('guests.id')
            ->orderByDesc('booking_count')
            ->limit(5)
            ->get()
            ->map(function ($guest) {
                return [
                    'name' => $guest->name,
                    'email' => $guest->email,
                    'phone' => $guest->phone,
                    'nr_of_bookings' => $guest->booking_count,
                ];
            });

        // Guests overview
        $guestsOverview = Guest::select('guests.name', DB::raw('COUNT(bookings.id) as booking_count'))
            ->join('bookings', 'guests.id', '=', 'bookings.guest_id')
            ->where('bookings.venue_id', $venue->id)
            ->whereBetween('bookings.check_in_date', [$startDate, $endDate])
            ->groupBy('guests.id', 'guests.name')
            ->orderByDesc('booking_count')
            ->get()
            ->map(function ($guest) {
                return [
                    'name' => $guest->name,
                    'nr_of_bookings' => $guest->booking_count,
                ];
            });

        // Guests Occupancy
        $guestsOccupancy = Booking::select(
            DB::raw('DATE(check_in_date) as date'),
            DB::raw('COUNT(*) as nr_of_bookings'),
            DB::raw('COUNT(DISTINCT guest_id) as nr_of_guests') // Changed this line
        )
            ->where('venue_id', $venue->id)
            ->whereBetween('check_in_date', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(check_in_date)'))
            ->orderBy('date')
            ->get()
            ->map(function ($occupancy) {
                return [
                    'date' => $occupancy->date,
                    'nr_of_bookings' => $occupancy->nr_of_bookings,
                    'nr_of_guests' => $occupancy->nr_of_guests,
                ];
            });

        $data = [
            'top_5_guests' => $topGuests,
            'guests_overview' => $guestsOverview,
            'guests_occupancy' => $guestsOccupancy,
        ];

        return response()->json(['data' => $data]);
    }

    public function fetchRevenueData(Request $request): \Illuminate\Http\JsonResponse
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

        $type = $request->input('type');
        if (!$type || !in_array($type, ['sales', 'coupons', 'detailed', 'discounts'])) {
            return response()->json(['error' => 'Invalid type provided'], 400);
        }

        switch ($type) {
            case 'sales':
                $data = $this->fetchSalesData($venue);
                break;
            case 'coupons':
                $data = $this->fetchCouponsData($venue);
                break;
            case 'discounts':
                $data = $this->fetchDiscountsData($venue);
                break;
            case 'detailed':
                $data = $this->fetchDetailedRevenueData($venue);
                break;
        }

        return response()->json($data, 200);
    }

    private function fetchSalesData($venue): array
    {
        // Fetching only completed orders
        $sales = Order::where('status', 'order_completed')
            ->where('restaurant_id', $venue->id)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $dates = $sales->pluck('date');

        // Convert totals to float values
        $totals = $sales->pluck('total')->map(function ($total) {
            return floatval($total);
        });

        return [
            'series' => [
                [
                    'name' => 'Sales',
                    'data' => $totals->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ],
            'currency' => '$'
        ];
    }

    private function fetchCouponsData($venue): array
    {
        // Fetching coupon data associated with completed orders
        $couponsUsed = DB::table('order_coupons')
            ->join('orders', 'orders.id', '=', 'order_coupons.order_id')
            ->where('orders.restaurant_id', $venue->id)
            ->where('orders.status', 'order_completed')
            ->select(DB::raw('DATE(orders.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->get();

        $dates = $couponsUsed->pluck('date');
        $counts = $couponsUsed->pluck('count');

        return [
            'series' => [
                [
                    'name' => 'Coupons Used',
                    'data' => $counts->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ]
        ];
    }

    private function fetchDiscountsData($venue): array
    {
        // Fetching discounts data associated with completed orders
        $discountsUsed = DB::table('order_discounts')
            ->join('orders', 'orders.id', '=', 'order_discounts.order_id')
            ->where('orders.restaurant_id', $venue->id)
            ->where('orders.status', 'order_completed')
            ->select(DB::raw('DATE(orders.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->get();

        $dates = $discountsUsed->pluck('date');
        $counts = $discountsUsed->pluck('count');

        return [
            'series' => [
                [
                    'name' => 'Discounts Used',
                    'data' => $counts->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ]
        ];
    }

    private function fetchDetailedRevenueData($venue): array
    {
        $startDate = '2023-08-10 12:45:52' ?? now();

        $orders = Order::where('status', 'order_completed')
            ->where('restaurant_id', $venue->id)
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as order_count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $couponCounts = DB::table('order_coupons')
            ->join('orders', 'orders.id', '=', 'order_coupons.order_id')
            ->where('orders.restaurant_id', $venue->id)
            ->where('orders.status', 'order_completed')
            ->where('orders.created_at', '>=', $startDate)
            ->select(DB::raw('DATE(orders.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->pluck('count', 'date');  // Get counts keyed by date

        $discountCounts = DB::table('order_discounts')
            ->join('orders', 'orders.id', '=', 'order_discounts.order_id')
            ->where('orders.restaurant_id', $venue->id)
            ->where('orders.status', 'order_completed')
            ->where('orders.created_at', '>=', $startDate)
            ->select(DB::raw('DATE(orders.created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->pluck('count', 'date');  // Get counts keyed by date

        $result = [];

        foreach ($orders as $order) {
            $result[] = [
                'date' => $order->date,
                'order_count' => $order->order_count,
                'sales' => floatval($order->total),
                'coupons_used' => $couponCounts[$order->date] ?? 0,  // Use the coupon count for the date or 0 if not set
                'discounts_used' => $discountCounts[$order->date] ?? 0,  // Use the discount count for the date or 0 if not set
                'currency' => '$'
            ];
        }

        return $result;
    }


    public function fetchDashboardData(Request $request): \Illuminate\Http\JsonResponse
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

        $type = $request->input('type');

        switch ($type) {
            case 'categories':
                $data = $this->fetchOrdersByCategory($venue);
                break;
            case 'products':
                $data = $this->fetchOrdersByProduct($venue);
                break;
            case 'orders':
                $data = $this->fetchOrdersByDate($venue);
                break;
            case 'sales':
                $data = $this->fetchSalesByDate($venue);
                break;
            default:
                return response()->json(['error' => 'Invalid type provided'], 400);
        }

        return response()->json($data, 200);
    }

    private function fetchOrdersByCategory($venue): array
    {
        $categories = DB::table('orders')
            ->join('order_products', 'orders.id', '=', 'order_products.order_id')
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->join('product_category', 'products.id', '=', 'product_category.product_id')
            ->join('categories', 'product_category.category_id', '=', 'categories.id')
            ->where('orders.status', 'order_completed')
            ->where('orders.restaurant_id', $venue->id)
            ->select('categories.title', DB::raw('COUNT(DISTINCT orders.id) as order_count'))
            ->groupBy('categories.title')
            ->get();

        return $categories->toArray();
    }

    private function fetchOrdersByProduct($venue): array
    {
        $products = DB::table('orders')
            ->join('order_products', 'orders.id', '=', 'order_products.order_id')
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->where('orders.status', 'order_completed')
            ->where('orders.restaurant_id', $venue->id)
            ->select('products.title', DB::raw('COUNT(DISTINCT orders.id) as order_count'))
            ->groupBy('products.title')
            ->get();

        return $products->toArray();
    }

    private function fetchOrdersByDate($venue): array
    {
        $dateFrom = '2023-08-10 12:45:52' ?? now();

        $orders = Order::where('restaurant_id', $venue->id)
            ->where('status', 'order_completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as order_count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $dates = $orders->pluck('date');
        $orderCounts = $orders->pluck('order_count');

        return [
            'series' => [
                [
                    'name' => 'Orders',
                    'data' => $orderCounts->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ]
        ];
    }


    private function fetchSalesByDate($venue): array
    {
        $dateFrom = '2023-08-10 12:45:52' ?? now();

        $sales = Order::where('restaurant_id', $venue->id)
            ->where('status', 'order_completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as sales_amount'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $dates = $sales->pluck('date');

        // Cast sales amounts to integers
        $salesAmounts = $sales->pluck('sales_amount')->map(function ($amount) {
            return floatval($amount);
        });

        return [
            'series' => [
                [
                    'name' => 'Sales',
                    'data' => $salesAmounts->toArray()
                ]
            ],
            'xaxis' => [
                'categories' => $dates->toArray()
            ],
            'currency' => '$'
        ];
    }

    public function getDashboardData(Request $request): \Illuminate\Http\JsonResponse
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

        $dates = $this->getDateRange($request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // General Data
        $totalRevenue = Booking::where('venue_id', $venue->id)
            ->whereIn('status', ['confirmed', 'completed']) // Include only relevant statuses
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('check_in_date', [$startDate, $endDate])
                    ->orWhereBetween('check_out_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('check_in_date', '<=', $startDate)
                            ->where('check_out_date', '>=', $endDate);
                    });
            })
            ->sum('total_amount');

        $noOfBookings = Booking::where('venue_id', $venue->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('check_in_date', [$startDate, $endDate])
                    ->orWhereBetween('check_out_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('check_in_date', '<=', $startDate)
                            ->where('check_out_date', '>=', $endDate);
                    });
            })
            ->count();

        $todayBookings = Booking::whereDate('check_in_date', Carbon::today())
            ->where('venue_id', $venue->id)
            ->count();

        $guests = Guest::whereHas('bookings', function($query) use ($startDate, $endDate, $venue) {
            $query->where('venue_id', $venue->id)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('check_in_date', [$startDate, $endDate])
                        ->orWhereBetween('check_out_date', [$startDate, $endDate])
                        ->orWhere(function ($subq) use ($startDate, $endDate) {
                            $subq->where('check_in_date', '<=', $startDate)
                                ->where('check_out_date', '>=', $endDate);
                        });
                });
        })->get();

        // Revenue Statistics
        $revenueStatistics = Booking::select(
            DB::raw('DATE(check_in_date) as date'),
            DB::raw('SUM(total_amount) as revenue')
        )
            ->where('venue_id', $venue->id)
            ->whereIn('status', ['confirmed', 'completed']) // Include only relevant statuses
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('check_in_date', [$startDate, $endDate])
                    ->orWhereBetween('check_out_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('check_in_date', '<=', $startDate)
                            ->where('check_out_date', '>=', $endDate);
                    });
            })
            ->groupBy(DB::raw('DATE(check_in_date)'))
            ->orderBy('date', 'asc')
            ->get();


        // Booking Schedule
        $bookingSchedule = Booking::select(
            DB::raw('DATE(check_in_date) as date'),
            DB::raw('count(*) as bookings')
        )
            ->where('venue_id', $venue->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('check_in_date', [$startDate, $endDate])
                    ->orWhereBetween('check_out_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('check_in_date', '<=', $startDate)
                            ->where('check_out_date', '>=', $endDate);
                    });
            })
            ->groupBy(DB::raw('DATE(check_in_date)'))
            ->orderBy('date', 'asc')
            ->get();

        // Booking List
        $bookingList = Booking::with(['guest', 'rentalUnit'])
            ->where('venue_id', $venue->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('check_in_date', [$startDate, $endDate])
                    ->orWhereBetween('check_out_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('check_in_date', '<=', $startDate)
                            ->where('check_out_date', '>=', $endDate);
                    });
            })
            ->orderBy('check_in_date', 'asc')
            ->get()
            ->map(function($booking) {
                return [
                    'id' => $booking->id,
                    'booking_id' => $booking->confirmation_code,
                    'guest_name' => $booking->guest->name,
                    'check_in_date' => $booking->check_in_date,
                    'check_out_date' => $booking->check_out_date,
                    'rental_unit_name' => $booking->rentalUnit?->name,
                    'total_amount' => $booking->total_amount,
                    'status' => $booking->status,
                    'currency' => $booking->rentalUnit?->currency, //
                ];
            });

        $primaryCurrency = RentalUnit::where('venue_id', $venue->id)
            ->select('currency')
            ->groupBy('currency')
            ->orderByRaw('COUNT(*) DESC')
            ->first()
            ?->currency;

        return response()->json([
            'general_data' => [
                'total_revenue' => $totalRevenue,
                'no_of_bookings' => $noOfBookings,
                'today_bookings' => $todayBookings,
                'guests' => $guests,
            ],
            'revenue_statistics' => $revenueStatistics,
            'booking_schedule' => $bookingSchedule,
            'booking_list' => $bookingList,
            'primary_currency' => $primaryCurrency,
        ]);
    }

    private function getDateRange(Request $request)
    {
        $startDate = $request->query('start');
        $endDate = $request->query('end');

        if (!$startDate || !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->toDateString();
            $endDate = Carbon::now()->endOfMonth()->toDateString();
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

}
