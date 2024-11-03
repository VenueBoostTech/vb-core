<?php

namespace App\Http\Controllers\v3\Whitelabel\ByBestShop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class BbGroupsController extends Controller
{
    public function groupProducts(Request $request, $group_id): \Illuminate\Http\JsonResponse
    {
        try {
            $group = Group::findOrFail($group_id);
            $products = Product::whereHas('groups', function($query) use ($group_id) {
                $query->where('group_id', $group_id);
            })->paginate(20);

            return response()->json([
                'group' => $group,
                'products' => $products,
                // Add other necessary data
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Group not found'], 404);
        }
    }
}
