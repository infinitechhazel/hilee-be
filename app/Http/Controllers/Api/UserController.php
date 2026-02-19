<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;

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
        if ($request->has('search') && !empty($request->search)) {
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
                'data' => $users
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
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
                    'email_verified_at'
                ])
                ->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user',
                'error' => $e->getMessage()
            ], 500);
        }
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
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
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
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating user status', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
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
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportPDF(Request $request)
    {
        try {
            $query = User::where('role', 'member')
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'address',
                    'fraternity_number',
                    'status',
                    'created_at',
                    'updated_at'
                ]);

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            }

            $users = $query->latest()->get();

            $stats = [
                'total' => $users->count(),
                'pending' => $users->where('status', 'pending')->count(),
                'approved' => $users->where('status', 'approved')->count(),
                'rejected' => $users->where('status', 'rejected')->count(),
                'deactivated' => $users->where('status', 'deactivated')->count(),
            ];

            $data = [
                'users' => $users,
                'stats' => $stats,
                'date' => now()->format('F d, Y'),
                'time' => now()->format('h:i A'),
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