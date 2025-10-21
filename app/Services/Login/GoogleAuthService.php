<?php

namespace App\Services\Login;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Validator;

class GoogleAuthService
{
    /**
     * Handle Google OAuth login/registration.
     */
    public function login(Request $request)
    {
        // Validate the access token
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'errors' => $validator->errors(),
                'status' => 422
            ];
        }

        try {
            // Fetch user data from Google API
            $response = Http::get('https://www.googleapis.com/oauth2/v3/userinfo', [
                'access_token' => $request->access_token,
            ]);

            if ($response->failed() || !isset($response['email'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid access token.',
                    'status' => 400
                ];
            }

            $userData = $response->json();
            $user = User::where('email', $userData['email'])->first();

            if (!$user) {
                // Register the user if they don't exist
                $user = User::create([
                    'name' => $userData['name'] ?? explode('@', $userData['email'])[0],
                    'email' => $userData['email'],
                    'password' => Hash::make(Str::random(16)), // Random password
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->update(['email_verified_at'=> now()]);
            }

            // Authenticate the user
            Auth::login($user);

            // Payload for JWT
            $payload = [
                'email' => $user->email,
                'name' => $user->name,
                'category' => $user->category ?? 'default',
                'email_verified' => $user->hasVerifiedEmail(),
            ];

            try {
                $token = JWTAuth::fromUser($user, ['guard' => 'user']);
            } catch (JWTException $e) {
                return [
                    'success' => false,
                    'error' => 'Could not create token',
                    'status' => 500
                ];
            }

            return [
                'success' => true,
                'token' => $token,
                'user' => $payload,
                'status' => 200
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'An error occurred during authentication.',
                'details' => $e->getMessage(),
                'status' => 500
            ];
        }
    }
}
