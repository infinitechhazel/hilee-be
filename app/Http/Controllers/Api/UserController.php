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
            $query = User::query();

            // Filter by role (admin/user)
            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }

            // Search by name or email
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
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
            $user = User::select([
                'id',
                'name',
                'email',
                'phone',       // was phone_number — fixed to match model
                'address',
                'city',        // was missing from select
                'zip_code',    // was missing from select
                'role',
                'email_verified',
                'created_at',
                'updated_at',
                'email_verified_at',
            ])->find($id);

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

    public function updateRole(Request $request, $id)
    {
        // Replaced updateStatus() — model has no status/rejection_reason fields
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:admin,user',
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

            $oldRole = $user->role;

            $user->update(['role' => $request->role]);

            Log::info('User role updated', [
                'user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $request->role,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully',
                'data' => $user->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating user role', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update role: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function statistics()
    {
        try {
            $stats = [
                'total' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'users' => User::where('role', 'user')->count(),
                'verified' => User::where('email_verified', true)->count(),
                'unverified' => User::where('email_verified', false)->count(),
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

    public function exportPDF(Request $request)
    {
        try {
            $query = User::select([
                'id',
                'name',
                'email',
                'phone',       // fixed from phone_number
                'address',
                'city',
                'zip_code',
                'role',
                'email_verified',
                'created_at',
                'updated_at',
            ]);

            if ($request->filled('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")    // fixed from phone_number
                      ->orWhere('address', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%");
                });
            }

            $users = $query->latest()->get();

            $stats = [
                'total' => $users->count(),
                'admins' => $users->where('role', 'admin')->count(),
                'users' => $users->where('role', 'user')->count(),
                'verified' => $users->where('email_verified', true)->count(),
                'unverified' => $users->where('email_verified', false)->count(),
            ];

            $data = [
                'users' => $users,
                'stats' => $stats,
                'generatedDateTime' => now()->format('F d, Y g:i A'),
            ];

            $pdf = Pdf::loadView('pdf.user-report', $data)
                ->setPaper('a4', 'portrait')
                ->setOption('margin-top', 15)
                ->setOption('margin-bottom', 15)
                ->setOption('margin-left', 15)
                ->setOption('margin-right', 15);

            return $pdf->download('users-report-' . now()->format('Y-m-d') . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error generating PDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
