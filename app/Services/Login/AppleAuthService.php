<?php

namespace App\Services\Login;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

class AppleAuthService
{
    /**
     * Handle Apple OAuth login/registration.
     *
     * @param Request $request
     * @return array
     */
    public function login(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'identity_token' => 'required|string',
            'name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'errors' => $validator->errors(),
                'status' => 422,
            ];
        }

        try {
            $identityToken = $request->identity_token;
            $name = $request->name;

            // Decode the Apple Identity Token
            $appleUserInfo = $this->decodeAppleIdentityToken($identityToken);
            Log::info($appleUserInfo);

            if (!$appleUserInfo || !isset($appleUserInfo['email'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid Apple token',
                    'status' => 400,
                ];
            }

            // Check if the user already exists
            $user = User::where('email', $appleUserInfo['email'])->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name ?? explode('@', $appleUserInfo['email'])[0],
                    'email' => $appleUserInfo['email'],
                    'password' => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                    'profile_completion' => 10,
                ]);
            } else {
                $user->update(['email_verified_at' => now()]);
            }

            // Authenticate the user
            Auth::login($user);

            // Generate JWT
            try {
                $token = JWTAuth::fromUser($user, ['guard' => 'user']);
            } catch (JWTException $e) {
                return [
                    'success' => false,
                    'error' => 'Could not create token',
                    'status' => 500,
                ];
            }

            return [
                'success' => true,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'profile_completion' => $user->profile_completion,
                'message' => 'Login successful via Apple',
                'status' => 200,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Apple Login failed',
                'details' => $e->getMessage(),
                'status' => 500,
            ];
        }
    }

    /**
     * Decode Apple Identity Token
     */
    private function decodeAppleIdentityToken($identityToken)
    {
        try {
            $tokenParts = explode(".", $identityToken);
            $payload = base64_decode(strtr($tokenParts[1], '-_', '+/'));
            return json_decode($payload, true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
