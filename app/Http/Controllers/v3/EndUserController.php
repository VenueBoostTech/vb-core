<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v1\OrdersController;
use App\Mail\PasswordConfirmationMail;
use App\Models\Address;
use App\Models\Chat;
use App\Models\Customer;
use App\Models\LoginActivity;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\StoreSetting;
use App\Models\Wallet;
use App\Models\WishlistItem;
use App\Services\EndUserService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EndUserController extends Controller
{
    protected EndUserService $endUserService;

    public function __construct(EndUserService $endUserService)
    {
        $this->endUserService = $endUserService;
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
        $orders = Order::where('customer_id', $customer->id)->paginate($perPage);

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
                'orderProducts',
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
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object

        // Fetch wallet info from CRM
        $crmResponse = $this->fetchWalletInfoFromCRM($user->id);

        if (!$crmResponse['success']) {
            return response()->json(['error' => 'Failed to fetch wallet information from CRM'], 500);
        }

        $crmData = $crmResponse['result']['endUser'];

        $walletInfo = new \stdClass();
        $walletInfo->balance = $crmData['wallet']['balance'] ?? 0;
        $walletInfo->currency = 'Lek';
        $walletInfo->walletActivities = $crmData['wallet']['transactions'] ?? [];
        $walletInfo->referralsList = $crmData['referrals'] ?? [];
        $walletInfo->loyaltyTier = $crmData['currentTierName'] ?? null;
        $walletInfo->referralCode = $crmData['referralCode'] ?? '';

        return response()->json(['wallet_info' => $walletInfo], 200);
    }

    private function fetchWalletInfoFromCRM($userId)
    {
        $BYBEST_SHOP_ID = '66551ae760ba26d93d6d3a32'; // ByBest Shop CRM ID

        $response = Http::get("https://crmapi.pixelbreeze.xyz/api/crm-web/customers/{$userId}", [
            'subAccountId' => $BYBEST_SHOP_ID,
        ]);

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

    public function getPromotions(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $perPage = $request->input('per_page', 15);
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

        // You can still keep the additional promotion filtering if needed
        $currentPromotions = $all_promotions->where('status', 1);
        $pastPromotions = $all_promotions->whereDate('end_time', '<', Carbon::now()->toDateTimeString());
        $usedPromotions = $all_promotions->whereHas('orders', function ($query) use ($user) {
            $query->where('customer_id', $user->customer->id);
        });

        return response()->json([
            'promotions' => $paginatedData,
            'current_promotions' => $currentPromotions,
            'past_promotions' => $pastPromotions,
            'used_promotions' => $usedPromotions,
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
            'current_password' => 'required',
            'new_password' => 'required',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        Mail::to($user->email)->send(new PasswordConfirmationMail($user->email, $user->name));


        return response()->json(['message' => 'Password updated successfully'], 200);
    }

    //getPreferences
    public function getPreferences(Request $request): \Illuminate\Http\JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object
        $preferences = [
            'marketing_sms' => $user?->endUserPreference?->marketing_sms ?? false,
            'marketing_email' => $user?->endUserPreference?->marketing_email ?? false,
            'promotion_sms' => $user?->endUserPreference?->promotion_sms ?? false,
            'promotion_email' => $user?->endUserPreference?->promotion_email ?? false,
        ];

        return response()->json(['preferences' => $preferences], 200);
    }

    //updatePreferences
    public function updatePreferences(Request $request): \Illuminate\Http\JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object

        $validator = Validator::make($request->all(), [
            'notifications.order_status' => 'required|boolean',
            'notifications.marketing_sms' => 'required|boolean',
            'notifications.marketing_email' => 'required|boolean',
            'notifications.promotion_sms' => 'required|boolean',
            'notifications.promotion_email' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        //if user has no endUserPreference, create one or update the existing one
        if (!$user->endUserPreference) {
            $user->endUserPreference()->create([
                'order_status' => $request->order_status,
                'marketing_sms' => $request->marketing_sms,
                'marketing_email' => $request->marketing_email,
                'promotion_sms' => $request->promotion_sms,
                'promotion_email' => $request->promotion_email,
            ]);
        } else {
            $user->endUserPreference->order_status = $request->order_status;
            $user->endUserPreference->marketing_sms = $request->marketing_sms;
            $user->endUserPreference->marketing_email = $request->marketing_email;
            $user->endUserPreference->promotion_sms = $request->promotion_sms;
            $user->endUserPreference->promotion_email = $request->promotion_email;
            $user->endUserPreference->save();
        }

        return response()->json(['message' => 'Preferences updated successfully'], 200);
    }

    //updateProfile

    public function updateProfile(Request $request): \Illuminate\Http\JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse; // If it's a JsonResponse, return it immediately
        }

        $user = $userOrResponse; // Now we know it's a User object

        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required',
            'country_id' => 'required',
            'street_address' => 'required',
            'city' => 'required',
            'state' => 'required',
            'postcode' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        //First add thee address in the address table
        $saveAddress = Address::create([
            'address_line1' => $request->street_address,
            'city' => $request->city,
            'state' => $request->state,
            'postcode' => $request->postcode,
            'country_id' => $request->country_id,
        ]);

        //Then update the user endUserAddress
        $user->endUserAddresses()->create([
            'address_id' => $saveAddress->id,
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Profile updated successfully'], 200);
    }


}
