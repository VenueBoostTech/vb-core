<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\BbSlider;
use App\Models\Photo;
use App\Models\Restaurant;
use App\Models\WhitelabelBanner;
use Illuminate\Support\Facades\Storage;
use App\Models\WhitelabelBannerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BannersController extends Controller
{
    private function getVenue(): ?Restaurant
    {
        if (!auth()->user()->restaurants->count()) {
            return null;
        }

        $apiCallVenueShortCode = request()->get('venue_short_code');
        if (!$apiCallVenueShortCode) {
            return null;
        }

        return auth()->user()->restaurants->where('short_code', $apiCallVenueShortCode)->first();
    }

    public function index(): JsonResponse
    {
        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $banners = $venue->whitelabelBanners()
            ->with('type')
            ->get()
            ->map(function ($banner) {
                return [
                    'id' => $banner->id,
                    'text' => $banner->text,
                    'url' => $banner->url,
                    'type' => $banner->type->type,
                    'status' => $banner->status,
                    'timer' => $banner->timer,
                ];
            });

        return response()->json(['data' => $banners], 200);
    }

    public function types(): JsonResponse
    {
        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $types = WhitelabelBannerType::all()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'type' => $type->type,
                    'description' => $type->description,
                ];
            });

        return response()->json(['data' => $types], 200);
    }


    public function uploadPhotos(Request $request)
    {
        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $sliders = BbSlider::where('venue_id', $venue->id)->get();
        $baseUrl = 'https://admin.bybest.shop/storage/sliders/';
        $uploadedCount = 0;
        $errors = [];

        foreach ($sliders as $slider) {
            if (!$slider->photo) {
                continue;
            }

            $oldPhotoUrl = $baseUrl . $slider->photo;

            try {
                $photoContents = file_get_contents($oldPhotoUrl);
                if ($photoContents !== false) {
                    $filename = Str::random(20) . '.jpg';
                    $requestType = 'slider';
                    $path = 'venue_gallery_photos/' . $venue->venueType->short_name . '/' . $requestType . '/' .
                        strtolower(str_replace(' ', '-', $venue->name . '-' . $venue->short_code)) . '/' . $filename;

                    Storage::disk('s3')->put($path, $photoContents);

                    $photo = new Photo([
                        'venue_id' => $venue->id,
                        'image_path' => $path,
                        'type' => $requestType
                    ]);
                    $photo->save();

                    $slider->update(['photo' => $path]);
                    $uploadedCount++;
                } else {
                    $errors[] = "Failed to fetch photo contents for slider ID: {$slider->id}";
                }
            } catch (\Exception $e) {
                $errors[] = "Error processing slider ID {$slider->id}: " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Processed {$uploadedCount} photos",
            'uploaded_count' => $uploadedCount,
            'errors' => $errors
        ]);
    }
}
