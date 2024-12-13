<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Models\BbSlider;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BbSliderController extends Controller
{
    public function index()
    {
        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return response()->json(['error' => 'Venue short code is required'], 400);
        }

        $venue = auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found'], 404);
        }

        $sliders = BbSlider::where('venue_id', $venue->id)->get() ->transform(function ($slider){
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
        });;
        return response()->json($sliders);
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

        if($request->title){
            $slider->title = $request->title; 
        }
        if($photo){
            $slider->photo = $photo;
        }
        if($request->url){
            $slider->url = $request->url;
        }
        if($request->description){
            $slider->description = $request->description;
        }
        if($request->button){
            $slider->button = $request->button;
        }
        if($request->text_button){
            $slider->text_button = $request->text_button;
        }
        if($request->slider_order){
            $slider->slider_order = $request->slider_order;
        }
        if($request->status){
            $slider->status = $request->status;
        }

        $slider->save();
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
