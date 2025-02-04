<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OmniStackGatewayMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = env('SN_BOOST_CORE_OMNI_STACK_GATEWAY_API_KEY');

        if ($request->header('SN-BOOST-CORE-OMNI-STACK-GATEWAY-API-KEY') !== $apiKey) {
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
