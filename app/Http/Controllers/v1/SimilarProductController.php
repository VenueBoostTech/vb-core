<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimilarProduct;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;

class SimilarProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSimilarProducts(Request $request)
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
            // Fetch similar product IDs for the given product_id (ensure it's a flat array)
            $similar_product_ids = SimilarProduct::where('bybest_id', $request->bybest_id)
                ->pluck('similar_products')
                ->map(function ($similar_products) {
                    return json_decode($similar_products, true);
                })
                ->flatten()  // Ensures that the result is a flat array
                ->toArray();  // Convert the result into a plain array if needed
        
            // Check if there are any similar product IDs to query
            if (count($similar_product_ids) > 0) {
                // Fetch products with titles using the retrieved similar product IDs
                $products = Product::whereIn('id', $similar_product_ids)
                    ->get(['id', 'title']);  // Only retrieve the product's ID and title
        
                // Transform the result to a simpler format (just product ID and title)
                $products_with_titles = $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                    ];
                });
        
                // Return the response with the similar products and their titles
                $similar_products = [
                    'bybest_id' => $request->bybest_id,
                    'similar_products' => $products_with_titles,
                ];
        
                return response()->json([
                    'message' => 'Similar products retrieved successfully',
                    'similar_products' => $similar_products,
                ], 200);
            } else {
                return response()->json(['message' => 'No similar products found']);
            }
        
        } catch (\Exception $e) {
            // Capture the exception and return an error response
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
        
        
        
    }


    public function updateOrcreatesimilarProducts(Request $request)
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
            'similar_products' => 'required|json',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        try {

            $similar_products = SimilarProduct::updateOrCreate(
                [
                    'bybest_id' => $request->bybest_id
                ],
                [ 
                    'similar_products' => $request->similar_products,
                ]
            );

            return response()->json(['message' => 'Similar products updated successfully', 'similar_products' => $similar_products], 200);

        }catch (\Exception $e) {
            \Sentry\captureException($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }

    }


    }



