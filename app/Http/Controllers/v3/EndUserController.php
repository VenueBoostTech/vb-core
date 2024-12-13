<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v1\OrdersController;
use App\Mail\PasswordConfirmationMail;
use App\Mail\EndUserPasswordConfirmationMail;
use App\Models\Address;
use App\Models\Chat;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\EndUserAddress;
use App\Models\Guest;
use App\Models\GuestMarketingSettings;
use App\Models\LoginActivity;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Receipt;
use App\Models\Restaurant;
use App\Models\State;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WishlistItem;
use App\Models\UserActivityLog;
use App\Models\Discount;
use App\Helpers\UserActivityLogger;
use App\Services\EndUserService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;

class EndUserController extends Controller
{
    protected EndUserService $endUserService;

    public function __construct(EndUserService $endUserService)
    {
        $this->endUserService = $endUserService;
    }

    /**
     * List all active countries
     */
    public function listCountries(): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        try {
            $countries = Country::where('active', true)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $countries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch countries: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List states by country
     */
    public function listStatesByCountry($countryId): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        try {
            $states = State::where('country_id', $countryId)
                ->where('active', 1)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $states
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch states: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List cities by state
     */
    public function listCitiesByState($stateId): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        try {
            $cities = City::where('states_id', $stateId)
                ->where('active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch cities: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getOrders(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;
        $customer = Customer::where('user_id', $user->id)->first();

        $perPage = $request->input('per_page', 15);
        $orders = Order::where('customer_id', $customer->id)->with('orderProducts.product')->paginate($perPage);

        $paginatedData = [
            'data' => $orders->items(),
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'total_pages' => $orders->lastPage(),
        ];

        return response()->json(['orders' => $paginatedData], 200);
    }


    public function getOrderDetails(Request $request, $orderId): \Illuminate\Http\JsonResponse
    {
        try {
            $userOrResponse = $this->endUserService->endUserAuthCheck();

            if ($userOrResponse instanceof JsonResponse) {
                return $userOrResponse;
            }

            $user = $userOrResponse;

            $validateOrder = Order::where('id', $orderId)->whereHas('customer', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->first();

            if (!$validateOrder) {
                return response()->json(['error' => "This order doesn't belong to the user."], 400);
            }

            $order = Order::with([
                'customer',
                'reservation',
                'restaurant',
                'orderProducts.product',
                'inventoryActivities',
                'orderDelivery',
                'paymentMethod',
                'promotion',
                'orderCoupons',
                'orderDiscounts',
                'address',
                'earn_points_history',
                'statusChanges',
                'orderSplitPayments',
                'physicalStore'
            ])->where('id', $orderId)->first();


            // Check if a chat exists for this order
            $chat = Chat::where('order_id', $orderId)
                ->where('end_user_id', $user->id)
                ->first();

            $response = [
                'order' => $order,
                'chat_id' => $chat ? $chat->id : null,
            ];

            return response()->json(['data' => $response], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Order not found'], 404);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getOne(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();
        if ($id != auth()->user()->customer->id) {
            return response()->json(['error' => 'Customer id does not match with the user'], 400);
        }
        return response()->json(['customer' => $userOrResponse?->customer], 200);
    }

    public function getActivities(Request $request): \Illuminate\Http\JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object

        // return empty array
        $activities = [];

        //first order
        $first_order = Order::with([
            'customer', 'restaurant'
        ])->whereHas('customer', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->orderBy('created_at', 'desc')
            ->first();

        //account created at
        $user_created = $user->created_at;
        //first loginActivities
        $loginActivities = LoginActivity::where('user_id', $user->id)->orderBy('created_at', 'desc')->first();

        $activities[] = [
            'description' => $first_order ? 'You placed your first order at ' . $first_order?->restaurant?->name . ' on ' . Carbon::parse($first_order?->created_at)->format('F d, Y H:i') : 'You have not placed any order yet',
        ];
        $activities[] = [
            'description' => 'You created your account on ' . Carbon::parse($user_created)->format('F d, Y H:i'),
        ];
        $activities[] = [
            'description' => 'You logged in on ' . Carbon::parse($loginActivities->created_at)->format('F d, Y H:i'),
        ];
        return response()->json(['activities' => $activities], 200);
    }

    public function getWishlist(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;
        $perPage = $request->input('per_page', 15); // Default to 15 items per page
        $isDemo = $request->input('demo', false); // Check if demo mode is requested

        if ($isDemo) {
            // Fetch random products in demo mode
            $randomProducts = Product::inRandomOrder()->take($perPage)->get(['id', 'title', 'brand_id', 'description', 'price', 'image_path']);

            // Format each product for the response
            $formattedProducts = $randomProducts->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'brand' => $product->brand ? $product->brand->name : null, // Assuming a brand relationship
                    'description' => $product->description,
                    'price' => $product->price,
                    'image' => $product->image_path,
                    // 'image' => $product->image_path ? Storage::disk('s3')->temporaryUrl($product->image_path, '+5 minutes') : null,
                ];
            });

            return response()->json([
                'wishlist' => [
                    'data' => $formattedProducts,
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => $formattedProducts->count(),
                    'total_pages' => 1,
                ]
            ], 200);
        }

        // Fetch the actual wishlist items if not in demo mode
        $wishlistItems = WishlistItem::where('customer_id', $user->customer->id)
            ->with('product')
            ->paginate($perPage);

        $paginatedData = [
            'data' => $wishlistItems->items(),
            'current_page' => $wishlistItems->currentPage(),
            'per_page' => $wishlistItems->perPage(),
            'total' => $wishlistItems->total(),
            'total_pages' => $wishlistItems->lastPage(),
        ];

        return response()->json(['wishlist' => $paginatedData], 200);
    }



    public function addToWishlist(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $existingItem = WishlistItem::where('customer_id', $user->customer->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingItem) {
            return response()->json(['message' => 'Product already in wishlist'], 409);
        }

        $wishlistItem = WishlistItem::create([
            'customer_id' => $user->customer->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json(['message' => 'Product added to wishlist', 'item' => $wishlistItem], 201);
    }

    public function removeFromWishlist(Request $request, $productId): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $deleted = WishlistItem::where('customer_id', $user->customer->id)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Product removed from wishlist']);
        } else {
            return response()->json(['message' => 'Product not found in wishlist'], 404);
        }
    }


    public function walletInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        // Perform end user authentication check
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        // If it's a JsonResponse, return it immediately
        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse; // Now we know it's a User object

        // Get the source from the request (default to 'bybest.shop_web' if not provided)
        $source = $request->query('source', 'bybest.shop_web');

        // Validate source
        if (!in_array($source, ['metrosuites', 'bybest.shop_web'])) {
            return response()->json(['error' => 'Invalid source provided'], 400);
        }

        // Fetch wallet info from CRM based on the source
        $crmResponse = $this->fetchWalletInfoFromCRM($user->id, $source);

        // If CRM response is unsuccessful, return an error response
        if (!$crmResponse['success']) {
            return response()->json(['error' => 'Failed to fetch wallet information from CRM'], 500);
        }

        // Extract data from CRM response
        $crmData = $crmResponse['result']['endUser'];

        // Get balance and calculate money value (100 points = 1 EUR)
        $balance = $crmData['wallet']['balance'] ?? 0;
        $moneyValue = $balance > 0 ? ($balance / 100) : 0;

        // Prepare wallet info response
        $walletInfo = new \stdClass();
        $walletInfo->balance = $balance;
        $walletInfo->currency = 'EUR';
        $walletInfo->money_value = number_format($moneyValue, 2, '.', ''); // Format to 2 decimal places
        $walletInfo->walletActivities = $crmData['wallet']['transactions'] ?? [];
        $walletInfo->referralsList = $crmData['referrals'] ?? [];
        $walletInfo->loyaltyTier = $crmData['currentTierName'] ?? null;
        $walletInfo->referralCode = $crmData['referralCode'] ?? '';

        return response()->json(['wallet_info' => $walletInfo], 200);
    }

    private function fetchWalletInfoFromCRM($userId, $source)
    {
        // Define subAccountId based on the source
        $subAccountIds = [
            'metrosuites' => '6730cb67d23dc622500cbf0d', // Metrosuites crm id
            'bybest.shop_web' => '66551ae760ba26d93d6d3a32', // ByBest Shop CRM ID
        ];

        // Ensure the source has a corresponding subAccountId
        if (!isset($subAccountIds[$source])) {
            return ['success' => false];
        }

        $subAccountId = $subAccountIds[$source];

        // Make the request to the CRM API with the appropriate subAccountId
        $response = Http::get("https://crmapi.pixelbreeze.xyz/api/crm-web/customers/{$userId}", [
            'subAccountId' => $subAccountId,
        ]);

        // Return response if successful, otherwise return failure
        if ($response->successful()) {
            return $response->json();
        }

        return ['success' => false];
    }


    public function getPaymentMethods(Request $request): \Illuminate\Http\JsonResponse
    {
        $staticPaymentMethods = [
            [
                'id' => 'cash',
                'name' => 'Cash on delivery',
                'type' => 'cash',
            ],
            [
                'id' => 'bkt',
                'name' => 'BKT',
                'type' => 'bank',
            ],
        ];

        return response()->json(['payment_methods' => $staticPaymentMethods], 200);
    }

    public function getGuestPayments(Request $request): \Illuminate\Http\JsonResponse
    {
        try {

            // Perform end user authentication check
            $userOrResponse = $this->endUserService->endUserAuthCheck();

            // If it's a JsonResponse, return it immediately
            if ($userOrResponse instanceof JsonResponse) {
                return $userOrResponse;
            }

            $user = $userOrResponse; // Now we know it's a User object

            // Get the guest ID from the authenticated user
            $guestId = $user->guest->id;

            $receipts = Receipt::with(['booking', 'rentalUnit', 'rentalUnit.gallery.photo'])
                ->whereHas('booking', function ($query) use ($guestId) {
                    $query->where('guest_id', $guestId);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedReceipts = $receipts->map(function ($receipt) {
                // Get the first photo from the rental unit's gallery
                $firstPhoto = $receipt->rentalUnit->gallery->first();
                $photoUrl = null;

                if ($firstPhoto && $firstPhoto->photo) {
                    $photoUrl = Storage::disk('s3')->temporaryUrl($firstPhoto->photo->image_path, '+5 minutes');
                }

                return [
                    'receipt_id' => $receipt->receipt_id,
                    'total_amount' => $receipt->total_amount,
                    'payment_date' => $receipt->created_at->format('Y-m-d H:i:s'),
                    'payment_status' => $receipt->status, // 'not_paid', 'partially_paid', 'fully_paid'
                    'rental_unit' => [
                        'id' => $receipt->rentalUnit->id,
                        'name' => $receipt->rentalUnit->name,
                        'photo_url' => $photoUrl
                    ],
                    'booking' => [
                        'id' => $receipt->booking->id,
                        'check_in_date' => $receipt->booking->check_in_date,
                        'check_out_date' => $receipt->booking->check_out_date,
                        'payment_method' => $receipt->booking->paid_with,
                        'prepayment_amount' => $receipt->booking->prepayment_amount,
                        'confirmation_code' => $receipt->booking->confirmation_code
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedReceipts
            ], 200);

        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching guest payment details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getPromotions(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $perPage = $request->get('per_page');
        $all_promotions = Promotion::with(['discounts', 'coupons'])
            ->where('venue_id', $user->customer->venue_id)
            ->where('status', 1)
            ->paginate($perPage);

        $paginatedData = [
            'data' => $all_promotions->items(),
            'current_page' => $all_promotions->currentPage(),
            'per_page' => $all_promotions->perPage(),
            'total' => $all_promotions->total(),
            'total_pages' => $all_promotions->lastPage(),
        ];

        $all_promotions = Promotion::with(['discounts', 'coupons']);

        // You can still keep the additional promotion filtering if needed
        $currentPromotions = $all_promotions->where('status', 1)->paginate($perPage);
        $pastPromotions = $all_promotions->whereDate('end_time', '<', Carbon::now()->toDateTimeString())->paginate($perPage);
        $usedPromotions = $all_promotions->whereHas('orders', function ($query) use ($user) {
            $query->where('customer_id', $user->customer->id);
        })->paginate($perPage);

        return response()->json([
            'promotions' => $paginatedData,
            'current_promotions' => $currentPromotions,
            'past_promotions' => $pastPromotions,
            'used_promotions' => $usedPromotions,
        ], 200);
    }

    public function getPromotionsGuest(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;
        $venueId = $user->guest?->restaurant_id;
        $perPage = $request->get('per_page', 10);
        $type = $request->get('type', 'all'); // Options: all, current, past, used

        $baseQuery = Promotion::with(['discounts','discounts.rental_unit', 'coupons'])
            ->where('venue_id', $venueId);

        switch ($type) {
            case 'current':
                $promotions = $baseQuery
                    ->where('status', 1)
                    ->whereDate('end_time', '>=', Carbon::now())
                    ->paginate($perPage);
                break;

            case 'past':
                $promotions = $baseQuery
                    ->whereDate('end_time', '<', Carbon::now())
                    ->paginate($perPage);
                break;

            case 'used':
                $promotions = $baseQuery
                    ->with([
                        'discounts' => function ($query) use ($user) {
                            $query->whereHas('bookings', function ($bookingQuery) use ($user) {
                                $bookingQuery->where('guest_id', $user->guest?->id);
                            })
                                ->with(['bookings' => function ($bookingQuery) use ($user) {
                                    $bookingQuery->select('id', 'discount_id', 'discount_price')
                                        ->where('guest_id', $user->guest?->id);
                                }]);
                        },
                        'discounts.rental_unit'
                    ])
                    ->whereHas('discounts.bookings', function ($query) use ($user) {
                        $query->where('guest_id', $user->guest?->id);
                    })
                    ->paginate($perPage);
                break;

            default: // 'all'
                $promotions = $baseQuery
                    ->where('status', 1)
                    ->paginate($perPage);
                break;
        }

        return response()->json([
            'message' => 'Promotions retrieved successfully',
            'data' => $promotions->items(),
            'current_page' => $promotions->currentPage(),
            'per_page' => $promotions->perPage(),
            'total' => $promotions->total(),
            'total_pages' => $promotions->lastPage(),
        ], 200);
    }

    //write reset password that take current password and new password and confirm password

    public function resetPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object

        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required',
            'newPassword' => 'required',
            'confirmPassword' => 'required|same:newPassword',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if (!Hash::check($request->currentPassword, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->newPassword);
        $user->save();

        $venueId = $user->guest?->restaurant_id ?? $user->customer->venue_id;
        $venue = Restaurant::where('id', $venueId)->first();
        $venueLogo = $venue->logo ? Storage::disk('s3')->temporaryUrl($venue->logo, '+8000 minutes') : null;
        Mail::to($user->email)->send(new EndUserPasswordConfirmationMail($user->email, $user->name, $venue->name, $venueLogo));

        UserActivityLogger::log(auth()->user()->id, 'Reset password');

        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    // get user security activities
    public function getUserActivities(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $activities = UserActivityLog::where('user_id', $user->id)->orderBy('created_at', 'desc')->limit(3)->get();

        return response()->json([
            'activities' => $activities
        ], 200);
    }

// Get Marketing Settings
    public function getMarketingSettings(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;
        $source = $request->query('source', 'bybest.shop_web');

        if ($source === 'bybest.shop_web') {
            // For ByBest Shop, use customer_id and use booking_* fields
            $marketingSettings = GuestMarketingSettings::firstOrCreate(
                ['customer_id' => $user->customer?->id], // Using customer_id for ByBest Shop
                [
                    'user_id' => $user->id,
                    'promotion_sms_notify' => true,
                    'promotion_email_notify' => true,
                    'booking_ms_notify' => true,  // Changed from booking_sms_notify to booking_ms_notify
                    'booking_ail_notify' => true // Changed from booking_email_notify to booking_ail_notify
                ]
            );
        } else {
            // Default to MetroSuites, use guest_id
            $marketingSettings = GuestMarketingSettings::firstOrCreate(
                ['guest_id' => $user->guest->id], // Using guest_id for MetroSuites
                [
                    'user_id' => $user->id,
                    'promotion_sms_notify' => true,
                    'promotion_email_notify' => true,
                    'booking_sms_notify' => true,
                    'booking_email_notify' => true
                ]
            );
        }

        return response()->json([
            'marketing_settings' => [
                'promotion_sms_notify' => $marketingSettings->promotion_sms_notify,
                'promotion_email_notify' => $marketingSettings->promotion_email_notify,
                'booking_sms_notify' => $marketingSettings->booking_sms_notify,  // Changed to booking_sms_notify
                'booking_email_notify' => $marketingSettings->booking_email_notify // Changed to booking_email_notify
            ]
        ], 200);
    }

    // Update Marketing Settings
    public function updateMarketingSettings(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;
        $source = $request->get('source'); // Add source to differentiate between MetroSuites and ByBest Shop

        // Validate the input data
        $validator = Validator::make($request->all(), [
            'promotion_sms_notify' => 'nullable|boolean',
            'promotion_email_notify' => 'nullable|boolean',
            'booking_sms_notify' => 'nullable|boolean', // Changed to booking_sms_notify
            'booking_email_notify' => 'nullable|boolean' // Changed to booking_email_notify
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        if ($source === 'bybest.shop_web') {
            // For ByBest Shop, use customer_id and use booking_* fields
            $marketingSettings = GuestMarketingSettings::updateOrCreate(
                ['customer_id' => $user->id], // Using customer_id for ByBest Shop
                [
                    'user_id' => $user->id,
                    'promotion_sms_notify' => $request->promotion_sms_notify,
                    'promotion_email_notify' => $request->promotion_email_notify,
                    'booking_sms_notify' => $request->booking_sms_notify, // Changed to booking_sms_notify
                    'booking_email_notify' => $request->booking_email_notify // Changed to booking_email_notify
                ]
            );
        } else {
            // Default to MetroSuites, use guest_id
            $marketingSettings = GuestMarketingSettings::updateOrCreate(
                ['guest_id' => $user->guest->id], // Using guest_id for MetroSuites
                [
                    'user_id' => $user->id,
                    'promotion_sms_notify' => $request->promotion_sms_notify,
                    'promotion_email_notify' => $request->promotion_email_notify,
                    'booking_sms_notify' => $request->booking_sms_notify, // Keep booking_sms_notify for MetroSuites
                    'booking_email_notify' => $request->booking_email_notify // Keep booking_email_notify for MetroSuites
                ]
            );
        }

        return response()->json([
            'message' => 'Marketing settings updated successfully',
            'marketing_settings' => [
                'promotion_sms_notify' => $marketingSettings->promotion_sms_notify,
                'promotion_email_notify' => $marketingSettings->promotion_email_notify,
                'booking_sms_notify' => $marketingSettings->booking_sms_notify,  // Changed to booking_sms_notify
                'booking_email_notify' => $marketingSettings->booking_email_notify // Changed to booking_email_notify
            ]
        ], 200);
    }

    //updateProfile

    public function getGuestProfile(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        // Get Guest
        $guest = Guest::where('user_id', $user->id)->first();
        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        // Get EndUserAddress with related address
        $endUserAddress = EndUserAddress::with('address')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        // Prepare address data
        $addressData = null;
        if ($endUserAddress && $endUserAddress->address) {
            $addressData = [
                'id' => $endUserAddress->address->id,
                'address_line1' => $endUserAddress->address->address_line1,
                'address_line2' => $endUserAddress->address->address_line2,
                'state' => $endUserAddress->address->state,
                'city' => $endUserAddress->address->city,
                'postcode' => $endUserAddress->address->postcode,
                'country' => $endUserAddress->address->country,
                'latitude' => $endUserAddress->address->latitude,
                'longitude' => $endUserAddress->address->longitude,
                'country_id' => $endUserAddress->address->country_id,
                'state_id' => $endUserAddress->address->state_id,
                'city_id' => $endUserAddress->address->city_id,
            ];
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name
            ],
            'guest' => [
                'id' => $guest->id,
                'email' => $guest->email,
                'phone' => $guest->phone,
            ],
            'address' => $addressData
        ], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        // First check if email is actually changing
        if ($request->email !== $user->email) {
            // Check both users and guests tables for email uniqueness
            $userExists = User::where('email', $request->email)
                ->where('id', '!=', $user->id)
                ->exists();

            $guestExists = Guest::where('email', $request->email)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($userExists || $guestExists) {
                return response()->json([
                    'errors' => [
                        'email' => ['The email has already been taken.']
                    ]
                ], 400);
            }
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|string|email',
            'street_address' => 'required|string',
            'cId' => 'required',
            'uId' => 'required',
            'aId' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            DB::beginTransaction();

            // Update User
            $user->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email
            ]);

            // Update Guest
            $guest = Guest::where('user_id', $user->id)->first();
            if ($guest) {
                $guest->update([
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address' => $request->street_address,
                ]);
            }

            $address = Address::where('id', $request->aId)->first();
            if($address) {
                $address->address_line1 = $request->street_address;
                $address->state = $request->state;
                $address->city = $request->city;
                $address->postcode = $request->zip;
                $address->country = $request->country;
                $address->save();

                EndUserAddress::updateOrCreate(
                    ['user_id' => $user->id],
                    ['address_id' => $address->id]
                );
            } else {
                $address = Address::create([
                    'address_line1' => $request->street_address,
                    'city' => $request->city,
                    'state' => $request->state,
                    'postcode' => $request->zip,
                    'country' => $request->country,
                ]);

                EndUserAddress::updateOrCreate(
                    ['user_id' => $user->id],
                    ['address_id' => $address->id]
                );
            }


            // Create or Update Address
            if ($request->has('address')) {
                // Get location names from IDs
                $country = Country::find($request->address['country_id']);
                $state = State::find($request->address['state_id']);
                $city = City::find($request->address['city_id']);

                $address = Address::create([
                    'address_line1' => $request->address['address_line1'],
                    'address_line2' => $request->address['address_line2'] ?? null,
                    'city' => $city->name,
                    'state' => $state->name,
                    'postcode' => $request->address['postcode'],
                    'country' => $country->name,
                    'country_id' => $request->address['country_id'],
                    'state_id' => $request->address['state_id'],
                    'city_id' => $request->address['city_id'],
                    'latitude' => $request->address['latitude'] ?? null,
                    'longitude' => $request->address['longitude'] ?? null
                ]);

                // Create or update EndUserAddress
                EndUserAddress::updateOrCreate(
                    ['user_id' => $user->id],
                    ['address_id' => $address->id]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ],
                'guest' => [
                    'id' => $guest->id,
                    'phone' => $guest->phone,
                    'email' => $guest->email,
                ],
                'address' => $address ?? null
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateCustomerProfile(Request $request): JsonResponse {

        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|string|email',
            'street_address' => 'required|string',
            'cId' => 'required',
            'uId' => 'required',
            'aId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            $user = User::where('id', $user->id)->first();
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->name = $request->first_name . ' ' . $request->last_name;
            $user->email = $request->email;
            $user->save();

            $customer = Customer::where('user_id', $user->id)->first();
            $customer->name = $request->first_name . ' ' . $request->last_name;
            $customer->email = $request->email;
            $customer->phone = $request->phone;
            $customer->address = $request->street_address;
            $customer->save();

            $address = Address::where('id', $request->aId)->first();
            $address->address_line1 = $request->street_address;
            $address->state = $request->state;
            $address->city = $request->city;
            $address->postcode = $request->zip;
            $address->country = $request->country;
            $address->save();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function getCustomerProfile(Request $request): JsonResponse
    {

        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $customer = Customer::where('user_id', $user->id)->first();
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $customerAddress = CustomerAddress::where('customer_id', $customer->id)->first();
        if (!$customerAddress) {
            return response()->json(['error' => 'Customer Address not found'], 404);
        }

        $address = Address::where('id', $customerAddress->address_id)->first();
        if (!$address) {
            return response()->json(['error' => 'Address not found'], 404);
        }

        $user = User::where('id', $request->uId)->first();  // Get the user
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
            'customer' => [
                'id' => $customer->id,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'address' => [
                'id' => $address->id,
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
                'state' => $address->state,
                'city' => $address->city,
                'postcode' => $address->postcode,
                'country' => $address->country,
                'is_for_retail' => $address->is_for_retail,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
                'country_id' => $address->country_id,
                'state_id' => $address->state_id,
                'city_id' => $address->city_id,
            ],
        ], 200);
    }

}
