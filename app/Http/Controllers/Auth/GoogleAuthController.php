<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Google_Client;
use Illuminate\Support\Facades\Hash;

class GoogleAuthController extends Controller
{
    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($request->token);

        if (!$payload) {
            return response()->json(['error' => 'Invalid Google token'], 401);
        }
        try {
            $user = User::where('google_id', $payload['sub'])
                ->orWhere('email', $payload['email'])
                ->first();
            if (!$user) {
                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'google_id' => $payload['sub'],
                    'avatar' => $payload['picture'],
                    'date_of_birth' => $payload['dob'] ?? null,
                    'status' => "inactive",
                    'phone_number' => $payload['phone_number'] ?? null,
                    'password' => Hash::make(Str::random(16)),
                ]);
            } else if (!$user->google_id) {
                // If found but no google_id set, link the account
                $user->update([
                    'google_id' => $payload['sub'],
                    'avatar' => $payload['picture'],
                ]);
            }

            if ($user->status != "active") {
                return response()->json([
                    'status' => false,
                    'message' => "You are not verified! contact admin",
                ]);
            }

            $user->update([
                'last_login_at' => now()
            ]);

            // Create token
            $token = $user->createToken('google')->plainTextToken;

            return response()->json([
                'user' => [
                    // 'id' => Crypt::encrypt($user->id),
                    'role' => $user->role,
                    'status' => $user->status,
                    'name' => $user->name,
                    'access_token' => $token,
                    'profile' => $user->avatar,
                    'initials' => strtoupper(substr($user->name, 0, 1)) .
                        (str_contains($user->name, ' ') ? strtoupper(substr(explode(' ', $user->name)[1], 0, 1)) : '')
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred',
            ], 500);
        }

    }

}