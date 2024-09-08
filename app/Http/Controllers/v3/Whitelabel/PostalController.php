<?php

namespace App\Http\Controllers\v3\Whitelabel;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Postal;
use App\Models\PostalPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostalController extends Controller
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

        $postals = $venue->postals()->get()->map(function ($postal) {
            return [
                'id' => $postal->id,
                'type' => $postal->type,
                'status' => $postal->status,
                'title' => $postal->title,
                'name' => $postal->name,
                'logo' => $postal->logo,
                'description' => $postal->description,
            ];
        });

        return response()->json(['data' => $postals], 200);
    }

    public function pricing(): JsonResponse
    {
        $venue = $this->getVenue();
        if (!$venue) {
            return response()->json(['error' => 'Venue not found or user not eligible'], 404);
        }

        $postalIds = $venue->postals()->pluck('id');

        $pricing = PostalPricing::whereIn('postal_id', $postalIds)
            ->with(['postal', 'city'])
            ->get()
            ->map(function ($price) {
                return [
                    'id' => $price->id,
                    'price' => $price->price,
                    'price_without_tax' => $price->price_without_tax,
                    'city' => $price->city->name,
                    'postal' => $price->postal->name,
                    'type' => $price->type,
                    'alpha_id' => $price->alpha_id,
                    'alpha_description' => $price->alpha_description,
                    'notes' => $price->notes,
                ];
            });

        return response()->json(['data' => $pricing], 200);
    }
}
