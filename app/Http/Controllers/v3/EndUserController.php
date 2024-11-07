<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use App\Http\Controllers\v1\OrdersController;
use App\Mail\PasswordConfirmationMail;
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
use App\Models\Promotion;
use App\Models\State;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WishlistItem;
use App\Models\UserActivityLog;
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

        Mail::to($user->email)->send(new PasswordConfirmationMail($user->email, $user->name));

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

    public function getMarketingSettings(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        // Get or create marketing settings
        $marketingSettings = GuestMarketingSettings::firstOrCreate(
            ['guest_id' => $user->guest->id],
            [
                'user_id' => $user->id,
                'promotion_sms_notify' => true,
                'promotion_email_notify' => true,
                'booking_sms_notify' => true,
                'booking_email_notify' => true
            ]
        );

        return response()->json([
            'marketing_settings' => [
                'promotion_sms_notify' => $marketingSettings->promotion_sms_notify,
                'promotion_email_notify' => $marketingSettings->promotion_email_notify,
                'booking_sms_notify' => $marketingSettings->booking_sms_notify,
                'booking_email_notify' => $marketingSettings->booking_email_notify
            ]
        ], 200);
    }

    //updateMarketingSettings
    public function updateMarketingSettings(Request $request): JsonResponse
    {
        $userOrResponse = $this->endUserService->endUserAuthCheck();

        if ($userOrResponse instanceof JsonResponse) {
            return $userOrResponse;
        }

        $user = $userOrResponse;

        $validator = Validator::make($request->all(), [
            'promotion_sms_notify' => 'required|boolean',
            'promotion_email_notify' => 'required|boolean',
            'booking_sms_notify' => 'required|boolean',
            'booking_email_notify' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $marketingSettings = GuestMarketingSettings::updateOrCreate(
            [
                'guest_id' => $user->guest->id
            ],
            [
                'user_id' => $user->id,
                'promotion_sms_notify' => $request->promotion_sms_notify,
                'promotion_email_notify' => $request->promotion_email_notify,
                'booking_sms_notify' => $request->booking_sms_notify,
                'booking_email_notify' => $request->booking_email_notify
            ]
        );

        return response()->json([
            'message' => 'Marketing settings updated successfully',
            'marketing_settings' => $marketingSettings
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
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['required', 'email'],
            'address.address_line1' => ['required', 'string'],
            'address.address_line2' => ['nullable', 'string'],
            'address.city_id' => ['required', 'exists:cities,id'],
            'address.state_id' => ['required', 'exists:states,id'],
            'address.postcode' => ['required', 'string'],
            'address.country_id' => ['required', 'exists:countries,id'],
            'address.latitude' => ['nullable', 'numeric'],
            'address.longitude' => ['nullable', 'numeric']
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
                    'phone' => $request->phone
                ]);
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
