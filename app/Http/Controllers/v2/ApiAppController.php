<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\ApiApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiAppController extends Controller
{

    public function createApp(Request $request): \Illuminate\Http\JsonResponse
    {

        // dd(ApiApp::all());
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:api_apps,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $apiKey = Str::random(32);
        $apiSecret = Str::random(64);

        $apiApp = ApiApp::create([
            'name' => $request->name,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'usage_count' => 0,
        ]);

        return response()->json([
            'message' => 'API App created successfully',
            'data' => $apiApp
        ], 201);
    }
}
