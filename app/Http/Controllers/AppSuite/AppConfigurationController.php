<?php

namespace App\Http\Controllers\AppSuite;

use App\Http\Controllers\Controller;
use App\Models\AppConfiguration;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppConfigurationController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'app_id' => 'required',
            'venue_id' => 'required',
            'app_name' => 'required',
            'main_color' => 'required',
            'button_color' => 'required',
            'logo_url' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        $venue = Restaurant::with(['venueType'])->find($request->venue_id);
        if ($request->hasFile('logo_url')) {
            $photoContents = file_get_contents($request->file('logo_url'));
            if ($photoContents !== false) {
                $filename = Str::random(20) . '.jpg';
                $path = 'venue_logo/' . $venue->venueType->short_name . '/' .
                    strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)) . '/' . $filename;
                Storage::disk('s3')->put($path, $photoContents);
            }
        }

        $appConfiguration = new AppConfiguration;
        $appConfiguration->vb_app_id = $request->app_id;
        $appConfiguration->venue_id = $request->venue_id;
        $appConfiguration->app_name = $request->app_name;
        $appConfiguration->main_color = $request->main_color;
        $appConfiguration->button_color = $request->button_color;
        $appConfiguration->logo_url = $path;

        $appConfiguration->save();

        return response()->json($appConfiguration, 200);
    }

    public function show($app_id): \Illuminate\Http\JsonResponse
    {
        $appConfiguration = AppConfiguration::where('vb_app_id', $app_id)->first();

        if ($appConfiguration) {
            return response()->json($appConfiguration, 200);
        } else {
            return response()->json(['error' => 'App Configuration not found'], 400);
        }
    }

    public function update(Request $request, $app_id): \Illuminate\Http\JsonResponse
    {
        // Validate the incoming request
        $request->validate([
            'venue_id' => 'required',
            'app_name' => 'required',
            'main_color' => 'required',
            'button_color' => 'required',
            'logo_url' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Find the existing AppConfiguration by ID
        $appConfiguration = AppConfiguration::where('vb_app_id', $app_id)->first();
        if(!$appConfiguration){
            return response()->json(['error' => 'App Configuration not found'], 400);
        }

        // Update the basic fields
        $appConfiguration->vb_app_id = $app_id;
        $appConfiguration->venue_id = $request->venue_id;
        $appConfiguration->app_name = $request->app_name;
        $appConfiguration->main_color = $request->main_color;
        $appConfiguration->button_color = $request->button_color;

        // Handle logo upload if a new logo is provided
        if ($request->hasFile('logo_url')) {
            $venue = Restaurant::with(['venueType'])->find($request->venue_id);
            $photoContents = file_get_contents($request->file('logo_url'));
            if ($photoContents !== false) {
                $filename = Str::random(20) . '.jpg';
                $path = 'venue_logo/' . $venue->venueType->short_name . '/' .
                    strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)) . '/' . $filename;
                Storage::disk('s3')->put($path, $photoContents);
                $appConfiguration->logo_url = $path; // Update the logo_url field
            }
        }

        // Save the updated configuration
        $appConfiguration->save();

        return response()->json($appConfiguration, 200);
    }


    public function destroy($app_id): \Illuminate\Http\JsonResponse
    {
        $appConfiguration = AppConfiguration::where('vb_app_id', $app_id)->first();

        if ($appConfiguration) {
            $appConfiguration->delete();
            return response()->json(['message' => 'App Configuration deleted successfully'], 200);
        } else {
            return response()->json(['error' => 'App Configuration not found'], 400);
        }
    }

}
