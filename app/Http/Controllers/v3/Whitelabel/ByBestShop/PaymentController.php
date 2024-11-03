<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use WebToPay;
use App\Models\Product;

class PaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        // Validate incoming request data using Validator facade
        $validator = Validator::make($request->all(), [
            'app_key' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|string|email',
            'country' => 'required|string',
            'city' => 'required|string',
            'payment_method' => 'required|string',
            'token' => 'required|string', // Simulated token
            'order_products' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        // Get validated data
        $validatedData = $validator->validated();

        // Calculate total amount
        $totalAmount = $this->getProductsTotal($validatedData['order_products']);

        // Prepare payment data
        $payseraData = [
            'projectid' => 'YOUR_PROJECT_ID', // Replace with your project ID
            'sign_password' => 'YOUR_SIGN_PASSWORD', // Replace with your sign password
            'amount' => $totalAmount, // Amount in currency units (e.g., EUR)
            'currency' => 'EUR', // Currency code
            'orderid' => uniqid(), // Unique order ID
            'description' => 'Payment for order from ' . $validatedData['first_name'] . ' ' . $validatedData['last_name'],
            'email' => $validatedData['email'],
            // 'return_url' => route('payment.success'), // URL to redirect after payment
            // 'cancel_url' => route('payment.cancel'), // URL to redirect if payment is canceled
            'accepturl' => route('payment.success'),
            'cancelurl' => route('payment.cancel'),
            'callbackurl' => route('payment.callback'),
        ];

        // Call WebToPay to build the request and redirect to payment
        try {
            // Build the request data
            $requestData = WebToPay::buildRequest($payseraData);

            // Redirect to the payment page
            WebToPay::redirectToPayment($requestData);
        } catch (WebToPayException $e) {
            return response()->json(['message' => 'Payment processing error: ' . $e->getMessage()], 500);
        }
    }

    private function getProductsTotal($order_products)
    {
        $total = 0;
        foreach ($order_products as $productData) {
            $product = Product::find($productData['product_id']);
            if (!$product) {
                throw new \Exception('Product not found');
            }
            $total += $product->price * $productData['product_quantity'];
        }
        return $total; // Return total amount
    }

    public function success(Request $request)
    {
        return response()->json(['message' => 'Payment successful', 'order_id' => $request->input('order_id')]);
    }

    public function cancel(Request $request)
    {
        return response()->json(['message' => 'Payment canceled']);
    }

    public function callback(Request $request)
    {
        return response()->json(['message' => 'Payment callback']);
    }
}
