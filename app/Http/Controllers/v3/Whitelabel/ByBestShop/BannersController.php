<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\WhitelabelBanner;
use App\Models\WhitelabelBannerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
