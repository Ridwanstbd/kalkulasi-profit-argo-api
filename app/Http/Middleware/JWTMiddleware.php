<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
class JWTMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(!$request->hasHeader('Authorization')){
            return response()->json([
                'success' => false,
                'message' => 'Authorization header not found'
            ],401);
        }
        try {
            $token = $request->header('Authorization');
            if (strpos($token, 'Bearer ') !== 0) {
                $token = 'Bearer ' . $token;
                $request->headers->set('Authorization', $token);
            }
            JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token not valid',
                'error' => $e->getMessage()
            ],401);
        }
        return $next($request);
    }
}
