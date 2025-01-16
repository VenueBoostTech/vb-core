<?php
namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Http\Controllers\Controller;
class PaymentMethodsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }
    
        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }
    
        $perPage = $request->input('per_page', 15);

        $paymentMethod = PaymentMethod::paginate($perPage);

        return response()->json([
           'data'=>$paymentMethod,
           'current_page' => $paymentMethod->currentPage(),
           'per_page' => $paymentMethod->perPage(),
           'total' => $paymentMethod->total(),
           'total_pages' => $paymentMethod->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }
    
        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $paymentMethod =  PaymentMethod::create([
           'name'=>$request->name, 
        ]);

        return response()->json([
            'data'=>$paymentMethod,
        ]);
    }

    public function update(Request $request, $id)
    {
        $apiCallVenueShortCode = $request->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }
    
        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $paymentMethod = PaymentMethod::find($id);
        if (!$paymentMethod) {
            return response()->json(['error' => 'Payment method not found'], 404);
        }

        $paymentMethod->update([
            'name'=>$request->name, 
        ]);

        return response()->json([
            'data'=>$paymentMethod,
        ]);

    }

    public function destroy($id)
    {
        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $paymentMethod = PaymentMethod::find($id);
        if (!$paymentMethod) {
            return response()->json(['error' => 'Payment method not found'], 404);
        }

        $paymentMethod->delete();

        return response()->json([
            'message' => 'Payment method deleted successfully',
            'data'=>$paymentMethod,
        ]); 

    }
}
