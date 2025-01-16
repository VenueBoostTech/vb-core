<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Models\BbSlider;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BBSliderController extends Controller
{
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
    
        // Query the sliders with pagination directly
        $sliders = BbSlider::where('venue_id', $venue->id)
            ->paginate($perPage)
            ->through(function ($slider) {
                return [
                    'id' => $slider->id,
                    'title' => $slider->title,
                    'photo' => $slider->photo,
                    'url' => $slider->url,
                    'description' => $slider->description,
                    'button' => $slider->button,
                    'text_button' => $slider->text_button,
                    'slider_order' => $slider->slider_order,
                    'created_at' => $slider->created_at,
                    'updated_at' => $slider->updated_at,
                ];
            });
    
        return response()->json([
            'data' => $sliders->items(), // Paginated items
            'current_page' => $sliders->currentPage(),
            'per_page' => $sliders->perPage(),
            'total' => $sliders->total(),
            'total_pages' => $sliders->lastPage(),
        ]);
    }
    

    public function store(Request $request)
    {
        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        // bybest_id get last and + 1
        $lastMenu = BbSlider::where('venue_id', $venue->id)->orderBy('bybest_id', 'desc')->first();
        $bybestId = $lastMenu ? $lastMenu->bybest_id + 1 : 1;

        $photo = $request->file('photo');
        if ($photo) {
            $photo = Storage::disk('s3')->putFile(
                "venue_gallery_photos/{$venue->id}",
                $request->file('photo')
            );
        }
        $slider = BbSlider::create([
            'venue_id' => $venue->id,
            'bybest_id' => $bybestId,
            'title' => $request->title,
            'photo' => $photo,
            'url' => $request->url,
            'description' => $request->description,
            'button' => $request->button,
            'text_button' => $request->text_button ?? '',
            'slider_order' => $request->slider_order,
            'status' => $request->status ?? 1,
        ]);
        $slider->photo = $photo;
        return response()->json($slider);
    }

    public function update(Request $request, $id)
    {
        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }


        $slider = BbSlider::find($id);
        if (!$slider) {
            return response()->json(['error' => 'Menu not found'], 404);
        }
       
        $photo = $request->file('photo');
        if ($photo) {
            $photo = Storage::disk('s3')->putFile(
                "venue_gallery_photos/{$venue->id}",
                $request->file('photo')
            );
        }

        $lastMenu = BbSlider::where('venue_id', $venue->id)->orderBy('bybest_id', 'desc')->first();
        $bybestId = $lastMenu ? $lastMenu->bybest_id + 1 : 1;

        $slider->update([
            'venue_id' => $venue->id,
            'bybest_id' => $bybestId,
            'title' => $request->title,
            'photo' => $photo,
            'url' => $request->url,
            'description' => $request->description,
            'button' => $request->button,
            'text_button' => $request->text_button ?? '',
            'slider_order' => $request->slider_order,
            'status' => $request->status ?? 1,
        ]);
        return response()->json($slider); 
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


        $menu = BbSlider::find($id);
        if (!$menu) {
            return response()->json(['error' => 'Menu not found'], 404);
        }
        $menu->delete();
        return response()->json(['message' => 'Menu deleted successfully']);
    }   
}
