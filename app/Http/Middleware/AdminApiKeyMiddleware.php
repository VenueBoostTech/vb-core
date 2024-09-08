<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $apiKey = env('SN_BOOST_CORE_ADMIN_API_KEY');

        if ($request->query('SN-BOOST-CORE-ADMIN-API-KEY') !== $apiKey) {
            return response()->json(
                [
                    'error' =>
                        'Access denied. The provided API key is either missing or invalid.
                        Please ensure you include a valid API key as a query parameter in your request.'
                ], 401
            );
        }

        return $next($request);
    }
}
