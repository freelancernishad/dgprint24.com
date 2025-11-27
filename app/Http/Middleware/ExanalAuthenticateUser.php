<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ExanalAuthenticateUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        try {
            // Decode and verify token using HS256 and the same JWT_SECRET as Node.js
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));

            // Attach payload to request so controllers can use it
            $request->merge(['user_payload' => (array)$decoded]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid or expired token',
                'error' => $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}
