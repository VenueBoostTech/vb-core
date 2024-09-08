<?php

namespace App\Http\Controllers\v3;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EndUserController extends Controller
{
    private $trackMasterApiUrl = '';
    private $trackMasterApiKey = '';

    public function __construct()
    {

    }
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {

        // return empty array
        $orders = [];
        return response()->json(['orders' => $orders], 200);

    }

    public function getOrderDetails(Request $request, $orderId): \Illuminate\Http\JsonResponse
    {
        // return empty object
        $orderDetails = [];
        return response()->json(['order_details' => $orderDetails], 200);
    }

    public function getOne(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        // return empty object
        $customer = [];
        return response()->json(['customer' => $customer], 200);
    }

    public function getActivities(Request $request): \Illuminate\Http\JsonResponse
    {
        // return empty array
        $activities = [];
        return response()->json(['activities' => $activities], 200);
    }

     public function getWishlist(Request $request): \Illuminate\Http\JsonResponse
    {
        // return empty array
        $wishlist = [];
        return response()->json(['wishlist' => $wishlist], 200);
    }

    public function walletInfo(Request $request): \Illuminate\Http\JsonResponse
    {

        // wallet should have these fields
        // ->balance
        // ->currency
        // ->walletActivities
        // -> referralsList
        // -> referralCode
        // -> loyaltyTier

        // return empty object
        $walletInfo = new \stdClass();
        $walletInfo->balance = 0;
        $walletInfo->currency = 'USD';
        $walletInfo->walletActivities = [];
        $walletInfo->referralsList = [];
        $walletInfo->loyaltyTier = '';
        $walletInfo->referralCode = '';
        return response()->json(['wallet_info' => $walletInfo], 200);
    }


    public function getPaymentMethods(Request $request): \Illuminate\Http\JsonResponse
    {
        // return empty array
        $paymentMethods = [];
        return response()->json(['payment_methods' => $paymentMethods], 200);
    }

    public function getPromotions(Request $request): \Illuminate\Http\JsonResponse
    {
        // current promotions
        // used promotions
        // promotions calendar


        // return empty array
        $promotions = new \stdClass();
        $promotions->current_promotions = [];
        $promotions->used_promotions = [];
        $promotions->promotions_calendar = [];
        return response()->json(['promotions' => $promotions], 200);
    }

}
