<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function verifyEmail(Request $request)
{
    $token = $request->query('token');

    if (!$token) {
        return response()->json([
            'success' => false,
            'message' => 'Verification token is required.',
        ], 400);
    }

    $user = User::where('verification_token', $token)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired verification token.',
        ], 404);
    }

    // Check expiry
    if ($user->verification_token_expiry && now()->isAfter($user->verification_token_expiry)) {
        return response()->json([
            'success' => false,
            'message' => 'Verification token has expired. Please register again.',
        ], 410);
    }

    // Mark as verified
    $user->update([
        'email_verified'            => true,
        'email_verified_at'         => now(),
        'verification_token'        => null,
        'verification_token_expiry' => null,
    ]);

    Log::info('Email verified', ['user_id' => $user->id, 'email' => $user->email]);

    return response()->json([
        'success' => true,
        'message' => 'Email verified successfully! You can now login.',
        'data'    => ['user' => $this->formatUser($user)],
    ]);
}
    public function account(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login to continue.',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data'    => ['user' => $this->formatUser($user)],
            ]);

        } catch (\Exception $e) {
            Log::error('Account fetch failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch account information.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'success' => true,
            'data'    => ['user' => $this->formatUser($user)],
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                      => 'required|string|max:255',
            'email'                     => 'required|string|email|max:255|unique:users',
            'password'                  => 'required|string|min:8',
            'password_confirmation'     => 'required|same:password',
            'phone'                     => 'nullable|string|max:11',
            'address'                   => 'nullable|string|max:500',
            'city'                      => 'nullable|string|max:255',
            'zip_code'                  => 'nullable|string|max:20',
            // Accept verification fields from Next.js — nullable so they don't break if absent
            'verification_token'        => 'nullable|string|max:255',
            'verification_token_expiry' => 'nullable|string',
            'email_verified'            => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::create([
                'name'                      => trim($request->name),
                'email'                     => strtolower(trim($request->email)),
                'password'                  => Hash::make($request->password),
                'phone'                     => $request->phone    ?: null,
                'address'                   => $request->address  ?: null,
                'city'                      => $request->city     ?: null,
                'zip_code'                  => $request->zip_code ?: null,
                'role'                      => 'user',
                'email_verified'            => false,
                'verification_token'        => $request->verification_token        ?: null,
                'verification_token_expiry' => $request->verification_token_expiry
                    ? \Carbon\Carbon::parse($request->verification_token_expiry)
                    : null,
            ]);

            Log::info('New user registered', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! You can now login.',
                'data'    => ['user' => $this->formatUser($user)],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('Failed login attempt', ['email' => $request->email, 'ip' => $request->ip()]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info('User logged in', ['user_id' => $user->id, 'email' => $user->email, 'role' => $user->role]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $request->user()->currentAccessToken()->delete();

            Log::info('User logged out', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json(['success' => true, 'message' => 'Logged out successfully.']);

        } catch (\Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Logout failed.', 'error' => $e->getMessage()], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Token refreshed', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Token refreshed.', 'data' => ['token' => $token]]);

        } catch (\Exception $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Token refresh failed.', 'error' => $e->getMessage()], 500);
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'address'        => $user->address,
            'city'           => $user->city,
            'zip_code'       => $user->zip_code,
            'role'           => $user->role,
            'email_verified' => $user->email_verified,
            'email_verified_at' => $user->email_verified_at,
            'created_at'     => $user->created_at,
            'updated_at'     => $user->updated_at,
        ];
    }
}