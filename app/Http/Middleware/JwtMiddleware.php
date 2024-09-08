<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;


class JwtMiddleware extends BaseMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
            }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return response()->json(['status' => 'error', 'message' => 'Expired token'], 401);
            }else{
                return response()->json(['status' => 'error', 'message' => 'Token not found'], 401);
            }
        }
        return $next($request);
    }
}
