<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JuanTapProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class JuanTapController extends Controller
{
    /**
     * Get the authenticated user's JuanTap profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $profile = JuanTapProfile::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'success' => true,
                    'message' => 'No profile found',
                    'data' => null
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $profile
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching JuanTap profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new JuanTap profile
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Check if profile already exists
            $existingProfile = JuanTapProfile::where('user_id', $user->id)->first();
            
            if ($existingProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile already exists. Use update instead.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'profile_url' => 'nullable|string|max:500',
                'qr_code' => 'nullable|string',
                'status' => 'sometimes|in:active,inactive',
                'subscription' => 'sometimes|in:free,basic,premium',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $profile = JuanTapProfile::create([
                'user_id' => $user->id,
                'profile_url' => $request->profile_url,
                'qr_code' => $request->qr_code,
                'status' => $request->status ?? 'inactive',
                'subscription' => $request->subscription ?? 'free',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile created successfully',
                'data' => $profile
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating JuanTap profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the JuanTap profile
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $profile = JuanTapProfile::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile not found. Create one first.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'profile_url' => 'nullable|string|max:500',
                'qr_code' => 'nullable|string',
                'status' => 'sometimes|in:active,inactive',
                'subscription' => 'sometimes|in:free,basic,premium',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Only update fields that are present in the request
            $updateData = [];
            if ($request->has('profile_url')) $updateData['profile_url'] = $request->profile_url;
            if ($request->has('qr_code')) $updateData['qr_code'] = $request->qr_code;
            if ($request->has('status')) $updateData['status'] = $request->status;
            if ($request->has('subscription')) $updateData['subscription'] = $request->subscription;

            $profile->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $profile->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating JuanTap profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete the JuanTap profile
     */
    public function destroy(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $profile = JuanTapProfile::where('user_id', $user->id)->first();

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            $profile->delete();

            return response()->json([
                'success' => true,
                'message' => 'Profile deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting JuanTap profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}