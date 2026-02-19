<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            // start query builder; we'll apply filters dynamically
            $query = User::query();

            // optional role filter
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // status filter only if the column actually exists in the table
            if (Schema::hasColumn('users', 'status') && $request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Search by name or other searchable fields
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                    // additional searchable columns could be chained here if needed
                });
            }

            $perPage = $request->get('per_page', 15);
            $users = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = User::where('role', 'member')
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'address',
                    'fraternity_number',
                    'status',
                    'role',
                    'rejection_reason',
                    'created_at',
                    'updated_at',
                    'email_verified_at',
                ])
                ->find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deactivate($id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete(); // soft delete

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully',
        ]);
    }

    // Reactivate user (restore soft-deleted)
    public function reactivate($id)
    {
        $user = User::onlyTrashed()->find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found or already active'], 404);
        }

        $user->restore(); // remove deleted_at

        return response()->json([
            'success' => true,
            'message' => 'User reactivated successfully',
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,approved,rejected,deactivated',
            'rejection_reason' => 'required_if:status,rejected,deactivated|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $updateData = [
                'status' => $request->status,
            ];

            // If rejected or deactivated, save reason
            if (in_array($request->status, ['rejected', 'deactivated']) && $request->rejection_reason) {
                $updateData['rejection_reason'] = $request->rejection_reason;
            } else {
                // Clear rejection reason for approved status
                $updateData['rejection_reason'] = null;
            }

            $user->update($updateData);

            Log::info('User status updated', [
                'user_id' => $user->id,
                'old_status' => $user->getOriginal('status'),
                'new_status' => $request->status,
                'reason' => $request->rejection_reason,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $user->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating user status', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: '.$e->getMessage(),
            ], 500);
        }
    }

    public function statistics(Request $request)
    {
        try {
            $stats = [
                'total' => User::where('role', 'member')->count(),
                'pending' => User::where('role', 'member')->where('status', 'pending')->count(),
                'approved' => User::where('role', 'member')->where('status', 'approved')->count(),
                'rejected' => User::where('role', 'member')->where('status', 'rejected')->count(),
                'deactivated' => User::where('role', 'member')->where('status', 'deactivated')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
