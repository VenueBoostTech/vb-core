<?php

namespace App\Services;

use App\Models\{Order, Cart, Customer, Product, Restaurant, Currency, PostalPricing};
use App\Enums\{OrderStatus, PaymentMethod};
use App\Exceptions\{CheckoutException, InventoryException};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Stevebauman\Location\Facades\Location;

class CheckoutService
{
    protected $inventoryService;
    protected $cartService;
    protected $customerService;
    protected $paymentService;
    protected $currencyService;

    public function __construct(
        InventoryService $inventoryService,
        CartService $cartService,
        CustomerService $customerService,
        PaymentService $paymentService,
        CurrencyService $currencyService
    ) {
        $this->inventoryService = $inventoryService;
        $this->cartService = $cartService;
        $this->customerService = $customerService;
        $this->paymentService = $paymentService;
        $this->currencyService = $currencyService;
    }

    protected function calculateShippingCost(array $shippingData, Restaurant $venue, $currency_all, $currency_eur)
    {
        $postalPricing = PostalPricing::where('city_id', $shippingData['city_id'])
            ->join('postals', 'postals.id', '=', 'postal_pricing.postal_id')
            ->where('postal_id', '2')
            ->first();

        if (!$postalPricing) {
            throw new CheckoutException('Shipping price not found for selected city');
        }

        return [
            'price' => $postalPricing->price,
            'price_eur' => $postalPricing->price / $currency_eur->exchange_rate
        ];
    }

    protected function checkIfSaleValid($start_date, $end_date): bool
    {
        if (!$start_date || !$end_date) {
            return false;
        }

        $now = Carbon::now();
        return $now->between(Carbon::parse($start_date), Carbon::parse($end_date));
    }

    protected function isValidCoupon($coupon, Product $product): bool
    {
        if (!$coupon) {
            return false;
        }

        // Check if coupon is valid for the product's brand
        $offer_valid = $this->checkIfOfferValidForProduct(
            $coupon->id,
            $product->brand_id,
            $product->id
        );

        return $offer_valid && !$coupon->isExpired();
    }

    protected function applyCouponDiscount(array &$result, $coupon, $currency_all, $currency_eur)
    {
        if ($coupon->type_id == 1) { // Percentage discount
            $discount_percent = ((float)$coupon->coupon_amount) / 100;
            $discount_amount = $result['subtotal'] * $discount_percent;
            $discount_amount_eur = $result['subtotal_eur'] * $discount_percent;

            $result['discount'] += $discount_amount;
            $result['discount_eur'] += $discount_amount_eur;
        }
        elseif ($coupon->type_id == 3) { // Fixed amount discount
            $result['discount'] += $coupon->coupon_amount * $currency_all->exchange;
            $result['discount_eur'] += $coupon->coupon_amount;
        }
    }

    protected function checkIfOfferValidForProduct($coupon_id, $brand_id, $product_id): bool
    {
        // Implement your existing offer validation logic
        return true; // Replace with actual validation
    }

    protected function formatShippingDetails(array $shipping): array
    {
        return [
            'name' => $shipping['first_name'],
            'surname' => $shipping['last_name'] ?? '',
            'state' => $shipping['country_id'],
            'city' => $shipping['city_id'],
            'phone_no' => $shipping['phone'],
            'email' => $shipping['email'] ?? '',
            'address' => $shipping['address_details'],
            'postal_code' => $shipping['postal_code'] ?? '0000'
        ];
    }

    protected function formatBillingDetails(array $billing): array
    {
        return [
            'name' => $billing['first_name'],
            'surname' => $billing['last_name'] ?? '',
            'state' => $billing['country_id'],
            'city' => $billing['city_id'],
            'phone_no' => $billing['phone'],
            'email' => $billing['email'] ?? '',
            'address' => $billing['address_details'],
            'postal_code' => $billing['postal_code'] ?? '0000'
        ];
    }

    protected function getLocationData(): array
    {
        $location = Location::get(request()->ip());

        return [
            'ip' => $location ? $location->ip : 'undefined',
            'latitude' => $location ? $location->latitude : 0,
            'longitude' => $location ? $location->longitude : 0,
            'country_code' => $location ? $location->countryCode : 'undefined',
            'city' => $location ? $location->cityName : 'undefined'
        ];
    }

    protected function generateOrderNumber(Restaurant $venue): string
    {
        $prefix = strtoupper(substr($venue->name, 0, 2));
        $randomDigits = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . time() . $randomDigits;
    }

    protected function generateDashboardUrl(Customer $customer, Order $order): string
    {
        return config('app.customer_dashboard_url') . "/orders/{$order->id}?" . http_build_query([
                'token' => $customer->active_session_token,
                'source' => 'checkout'
            ]);
    }

    protected function resolvePaymentMethod(string $method): int
    {
        $methods = [
            'cash' => PaymentMethod::CASH,
            'bkt' => PaymentMethod::BKT,
            'paysera' => PaymentMethod::PAYSERA
        ];

        return $methods[$method] ?? PaymentMethod::CASH;
    }

    public function handlePaymentWebhook(string $provider, array $payload)
    {
        DB::beginTransaction();
        try {
            $order = Order::findOrFail($payload['order_id']);

            if ($this->paymentService->validateWebhook($provider, $payload)) {
                // Update order status
                $order->payment_status = 'paid';
                $order->status = OrderStatus::PAYMENT_RECEIVED;
                $order->save();

                // Convert reservations to actual inventory reductions
                $this->inventoryService->convertReservationsToReductions($order);

                // Notify customer
                $this->customerService->notifyOrderConfirmed($order);

                DB::commit();
                return ['success' => true];
            }

            throw new CheckoutException('Invalid webhook payload');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment webhook failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            throw $e;
        }
    }

    public function getPostalPricing(int $venueId, int $cityId, int $countryId)
    {
        $venue = Restaurant::findOrFail($venueId);
        $postalIds = $venue->postals()->pluck('id');

        return PostalPricing::whereIn('postal_id', $postalIds)
            ->where('city_id', $cityId)
            ->with(['postal', 'city'])
            ->get()
            ->map(function ($price) {
                return [
                    'id' => $price->id,
                    'price' => $price->price,
                    'price_without_tax' => $price->price_without_tax,
                    'city' => $price->city->name,
                    'postal' => $price->postal->name,
                    'type' => $price->type,
                    'alpha_id' => $price->alpha_id,
                    'alpha_description' => $price->alpha_description,
                    'notes' => $price->notes,
                ];
            });
    }

    public function validateCoupon(string $code, string $cartId, int $venueId)
    {
        $cart = Cart::findOrFail($cartId);
        $coupon = Coupon::where('code', $code)
            ->where('venue_id', $venueId)
            ->where('status', true)
            ->first();

        if (!$coupon) {
            throw new CheckoutException('Invalid coupon code');
        }

        if ($coupon->isExpired()) {
            throw new CheckoutException('Coupon has expired');
        }

        // Validate coupon against cart items
        foreach ($cart->products as $item) {
            if (!$this->isValidCoupon($coupon, Product::find($item['product_id']))) {
                throw new CheckoutException('Coupon not valid for some products in cart');
            }
        }

        return [
            'coupon' => $coupon,
            'discount_type' => $coupon->type_id,
            'discount_amount' => $coupon->coupon_amount
        ];
    }
}
