<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Models\BbMainMenu;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BBMenusController extends Controller
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

        $menus = BbMainMenu::where('venue_id', $venue->id)->get() ->transform(function ($menu){
            $photo = $menu->photo;
            if (strpos($photo, 'bb-main-menu/') === 0) {
                $photo = Storage::disk('s3')->temporaryUrl($photo, '+5 minutes');
            }
            return [
                'id' => $menu->id,
                'title' => $menu->title,
                'photo' => $photo,
                'order' => $menu->order,
                'link' => $menu->link,
                'type_id' => $menu->type_id,
                'group_id' => $menu->group_id,
                'bybest_id' => $menu->bybest_id,
                'created_at' => $menu->created_at,
                'updated_at' => $menu->updated_at,
                'deleted_at' => $menu->deleted_at
            ];
        });;
        return response()->json($menus);
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
        $lastMenu = BbMainMenu::where('venue_id', $venue->id)->orderBy('bybest_id', 'desc')->first();
        $bybestId = $lastMenu ? $lastMenu->bybest_id + 1 : 1;

        $photo = $request->file('photo');
        if ($photo) {
            $photo = Storage::disk('s3')->putFile(
                "bb-main-menu/{$venue->id}",
                $request->file('photo')
            );
        }
        $menu = BbMainMenu::create([
            'venue_id' => $venue->id,
            'bybest_id' => $bybestId,
            'type_id' => $request->type_id,
            'group_id' => $request->group_id,
            'title' => ['en' => $request->title],
            'photo' => $photo,
            'order' => $request->order,
            'link' => $request->link
        ]);
        $photo = $menu->photo;
        if (strpos($photo, 'bb-main-menu/') === 0) {
            $photo = Storage::disk('s3')->temporaryUrl($photo, '+5 minutes');
        }
        $menu->photo = $photo;
        return response()->json($menu);
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


        $menu = BbMainMenu::find($id);
        if (!$menu) {
            return response()->json(['error' => 'Menu not found'], 404);
        }

        $photo = $request->file('photo');
        if ($photo) {
            $photo = Storage::disk('s3')->putFile(
                "bb-main-menu/{$venue->id}",
                $request->file('photo')
            );
        }

        if($request->has('title')){
            $menu->title = ['en' => $request->title];
        }
        if($request->has('order')){
            $menu->order = $request->order;
        }
        if($request->has('link')){
            $menu->link = $request->link;
        }
        if($request->has('type_id')){
            $menu->type_id = $request->type_id;
        }
        if($request->has('group_id')){
            $menu->group_id = $request->group_id;
        }

        if($photo){
            $menu->photo = $photo;
        }

        $menu->save();

        $photo = $menu->photo;
        if (strpos($photo, 'bb-main-menu/') === 0) {
            $photo = Storage::disk('s3')->temporaryUrl($photo, '+5 minutes');
        }
        $menu->photo = $photo;
        return response()->json($menu);
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


        $menu = BbMainMenu::find($id);
        if (!$menu) {
            return response()->json(['error' => 'Menu not found'], 404);
        }
        $menu->delete();
        return response()->json(['message' => 'Menu deleted successfully']);
    }
}
