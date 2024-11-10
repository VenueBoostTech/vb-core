<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientPortalApiKeyMiddleware
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
        $apiKey = env('SN_BOOST_CORE_CLIENT_PORTAL_API_KEY');

        if ($request->query('SN_BOOST_CORE_CLIENT_PORTAL_API_KEY') !== $apiKey) {
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
