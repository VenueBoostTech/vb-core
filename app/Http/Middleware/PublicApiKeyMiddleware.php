<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PublicApiKeyMiddleware
{
    public function handle($request, Closure $next)
    {
        $headers = $request->headers->all();

        $apiKey = $request->header('X-Api-Key');
        $apiSecret = $request->header('X-Api-Secret');

        // Check if the headers are not found in the request, then check in $headers
        if (empty($apiKey) || empty($apiSecret)) {
            if (isset($headers['X-Api-Key'][0]) && isset($headers['X-Api-Secret'][0])) {
                $apiKey = $headers['X-Api-Key'][0];
                $apiSecret = $headers['X-Api-Secret'][0];
            } else {
                return response()->json(['error' => 'API key and secret are required'], 401);
            }
        }



        if (!$apiKey || !$apiSecret) {
            return response()->json(['error' => 'API key and secret are required'], 401);
        }

        $app = DB::table('api_apps')->where('api_key', $apiKey)->where('api_secret', $apiSecret)->first();
        if (!$app) {
            // compose error to include the values of the headers
            $errorMessage = 'Invalid API key or secret: API Key = ' . $apiKey . ', API Secret = ' . $apiSecret;

            return response()->json(['error' => $errorMessage], 401);
        }

        // Inside the handle method, after updating the usage_count
        if ($app->usage_count >= 10000) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }

        // Update usage count
        DB::table('api_apps')->where('id', $app->id)->increment('usage_count');

        return $next($request);
    }
}
