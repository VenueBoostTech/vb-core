<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CartSuggestion;
use App\Models\Product;

class BbCartSuggestionProductsController extends Controller
{
    public function getCartSuggestionProducts(Request  $request)
    {
      $cart_suggestions = CartSuggestion::whereIn('bybest_id',(array) json_decode($request->bybest_id))->get();

        if(empty($cart_suggestions)){
                return response()->json(['error' => 'Cart Suggestions products not found'], 404);
        }


        $products = $cart_suggestions->map(function ($cart_suggestion){
            $products =  json_decode($cart_suggestion->cart_suggestions);
            return Product::whereIn('id',$products)->get();
        })->flatten();

        if(empty($products)){
                return response()->json(['error' => 'Products not found'], 404);
        }
      
        $response_data = [
            'cart_suggestions'=>$products,
        ];

      return response()->json($response_data);
    }
}
