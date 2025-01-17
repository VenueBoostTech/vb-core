<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\VbStoreAttribute;
use App\Models\VbStoreAttributeOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VbStoreAttributeController extends Controller
{
    /**
     * Display a listing of the attributes.
     */
    public function index()
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
        $attributes = VbStoreAttribute::with('type')->orderBy('id', 'desc')->get();
        return response()->json(['data' => $attributes], 200);
    }

    /**
     * Store a newly created attribute in storage.
     */
    public function store(Request $request)
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
        $request->validate([
            'type_id' => 'required|exists:vb_store_attributes_types,id',
            'attr_name' => 'required|string|max:255',
            'attr_url' => 'required|url|max:255',
            'attr_description' => 'nullable|string',
        ]);

        $attribute = VbStoreAttribute::create($request->all());
        return response()->json($attribute, 201);
    }

    /**
     * Display the specified attribute.
     */
    public function show($id)
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
        $attribute = VbStoreAttribute::with('type')->findOrFail($id);
        return response()->json($attribute);
    }

    public function getAttributesOptions($id)
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

        $attribute = VbStoreAttribute::where('id',$id)->first();

        if(!$attribute){
            return response()->json(['error' => 'Attribute not found'], 404);
        }
        $options = $attribute->options;
        return response()->json(['data' => $options], 200);
    }


    public function updateAttributeOptions(Request $request)
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
        $request->validate([
            'option_name' => 'required',
            'option_url' => 'required|string|max:255',
            'option_description' => 'required|string',
            'option_photo'=>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $attribute_option = VbStoreAttributeOption::where('id',$request->input('id'))->first();
        
        if(!$attribute_option){
            return response()->json(['error' => 'Attribute option not found'], 404);
        }

        $attribute_option->update($request->all());

        return response()->json($attribute_option, 200);
    }


    public function deleteAttributeOption($id)
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

        $attribute_option = VbStoreAttributeOption::where('id',$id)->first();

        if(!$attribute_option){
            return response()->json(['error' => 'Attribute option not found'], 404);
        }

        $attribute_option->delete();

        return response()->json(['message' => 'Attribute option deleted Successfully'], 200);

    }
    /**
     * Update the specified attribute in storage.
     */
    public function update(Request $request, $id)
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
        $request->validate([
            'type_id' => 'required|exists:vb_store_attributes_types,id',
            'attr_name' => 'required|string|max:255',
            'attr_url' => 'required|string|max:255',
            'attr_description' => 'nullable|string',
        ]);

        $attribute = VbStoreAttribute::findOrFail($id);
        $attribute->update($request->all());
        return response()->json($attribute);
    }

    /**
     * Remove the specified attribute from storage.
     */
    public function destroy($id)
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
        $attribute = VbStoreAttribute::findOrFail($id); 
        DB::table('vb_store_attributes_options')
            ->where('attribute_id', $id)
            ->delete();
        $attribute->delete();
        return response()->json(['message' => 'Attribute deleted Successfully'], 204);
    }
}
