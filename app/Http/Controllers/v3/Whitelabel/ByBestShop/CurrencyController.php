<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\AccountingFinance\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    
    public function index(){

        $apiCallVenueShortCode = request()->get('venue_short_code');

        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $currency = Currency::all();

        return response()->json($currency, 200);
    }


    public function store(Request $request){


        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }



       $request->validate([
          'currency_alpha' => 'required',
          'code'=>'required',
          'name'=>'required|string|max:255',
          'exchange_rate'=>'required|numeric',
          'is_primary'=>'required|boolean',
       ]);


        $currency = Currency::create([
            'currency_alpha' => $request->currency_alpha,
            'code'=>$request->code,
            'name'=>$request->name,
            'exchange_rate'=>$request->exchange_rate,
            'is_primary'=>$request->is_primary,
        ]);
        return response()->json($currency, 200);
    }


    public function update(Request $request, $id){

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $request->validate([
            'currency_alpha' => 'required',
            'code'=>'required',
            'name'=>'required|string|max:255',
            'exchange_rate'=>'required|numeric|between:0,12',
            'is_primary'=>'required|boolean|between:0,1',
         ]);
        $currency = Currency::find($id);
        if(!$currency){
            return response()->json('Currency not found', 404);
        }
        $currency->update([
            'currency_alpha' => $request->currency_alpha,
            'code'=>$request->code,
            'name'=>$request->name,
            'exchange_rate'=>$request->exchange_rate,
            'is_primary'=>$request->is_primary,
        ]);
        return response()->json($currency, 200);
    }
    public function destroy($id){


        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }




        $currency = Currency::find($id);
        if(!$currency){
            return response()->json('Currency not found', 404);
        }
        $currency->delete();
        return response()->json('Currency Destroy Successfully', 200);
    }
}
