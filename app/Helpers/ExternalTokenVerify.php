<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;

class ExternalTokenVerify
{
    public static function verifyExternalToken($token)
    {


        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        // IMPORTANT: This MUST be the secret key used by the service that issued the token.
        // Do NOT use the JWT_SECRET from your .env file unless they are the same.
        $correctSecretKey = "kJlmH8uiBQKGXzsr83mE9nNF3bCMbsKeTsjqYCpCLOBqjRNQpLZQSebIAE2sqfSx";
        $algorithm = 'HS256'; // This must match the algorithm in the token's header

        try {
            // This will decode and, most importantly, VERIFY the signature
            $decoded = JWT::decode($token, new Key($correctSecretKey, $algorithm));

            return $decoded;


        } catch (SignatureInvalidException $e) {
            // This will be caught if the secret key is WRONG
            Log::error("JWT Signature Invalid: " . $e->getMessage());
            return  'Token is invalid';

        } catch (\Exception $e) {
            // Catch other errors like expired token
            Log::error("JWT Error: " . $e->getMessage());
            return  'Could not decode token';
        }
    }
}
