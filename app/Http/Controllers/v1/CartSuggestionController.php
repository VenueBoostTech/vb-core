<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CartSuggestion;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;

class CartSuggestionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCartSuggestion(Request $request)
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
            // Fetch and decode cart_suggestions
            $cart_suggestions = CartSuggestion::where( 'bybest_id', $request->bybest_id)
                ->pluck('cart_suggestions')
                ->map(function ($suggestion) {
                    return json_decode($suggestion, true);  // Decode each JSON entry
                })
                ->flatten()  // Flatten the array of arrays
                ->toArray(); // Convert to a plain array
        
            if (count($cart_suggestions) > 0) {
                // Fetch products with titles using the retrieved similar product IDs
                $products = Product::whereIn('id', $cart_suggestions)->get(['id', 'title']);
        
                // Transform the result to a simpler format (just product ID and title)
                $products_with_title = $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                    ];
                });
        
                $cart_suggestions = [
                    'bybest_id' => $request->bybest_id,
                    'similar_products' => $products_with_title,
                ];
        
                return response()->json(['message' => 'Cart suggestions retrieved successfully', 'cart_suggestions' => $cart_suggestions], 200);
            } else {
                return response()->json(['message' => 'No cart suggestions found']);
            }
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
        
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upateOrcreateCartSuggestion(Request $request)
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
            'bybest_id' => 'required|integer',
            'cart_suggestions' => 'required|json',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {
            $cart_suggestion = CartSuggestion::updateOrCreate(
                [
                    'bybest_id' => $request->bybest_id,
                ],
                [
                    'cart_suggestions' => $request->cart_suggestions,
                ]
            );

            return response()->json(['message' => 'Cart suggestion updated successfully', 'cart_suggestion' => $cart_suggestion], 200);
        } catch (\Exception $e) {
            // Handle the exception if needed
            return response()->json(['error' => 'Unable to update or create cart suggestion'], 500);
        }
        
    }


}
