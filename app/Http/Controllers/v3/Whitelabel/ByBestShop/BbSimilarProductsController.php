<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SimilarProduct;

class BbSimilarProductsController extends Controller
{
    public function getSimilarProducts(Request  $request)
    {
      $similar_products = SimilarProduct::whereIn('bybest_id',(array) json_decode($request->bybest_id))->get();

    if(empty($similar_products)){
            return response()->json(['error' => 'Similar products not found'], 404);
    }


     $products = $similar_products->map(function ($similar_product){
          $products = json_decode($similar_product->similar_products);
          return Product::whereIn('id',$products)->get();
       })->flatten();

       if(empty($products)){
            return response()->json(['error' => 'Products not found'], 404);
       }
      
       $response_data = [
        'similar_products'=>$products,
       ];

      return response()->json($response_data);
    }
}
