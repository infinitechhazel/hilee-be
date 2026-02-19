<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    /** @var array<string> */
    protected const VALID_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'completed', 'cancelled'];

    /**
     * GET /api/admin/orders
     * Get all orders (admin only) with pagination and filters.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Admin access required',
                ], 403);
            }

            $perPage = $request->input('per_page', 15);
            $status  = $request->input('status');
            $search  = $request->input('search');

            $query = Order::with(['orderItems.product', 'user'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            // Search by order code, customer name, or email
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $orders,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching admin orders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
            ], 500);
        }
    }

    /**
     * GET /api/admin/orders/{orderCode}
     * Get a specific order by order_code (admin only).
     */
    public function show(Request $request, $orderCode)
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Admin access required',
                ], 403);
            }

            $order = Order::with(['orderItems.product', 'user'])
                ->where('order_code', $orderCode)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching admin order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order',
            ], 500);
        }
    }

    /**
     * POST /api/admin/orders/{orderCode}/status
     * Update the status of a single order by order_code (admin only).
     *
     * Body: { "status": "confirmed" }
     *
     * Rules enforced here:
     *   - cancelled orders cannot be changed to anything else
     *   - completed orders cannot be changed to anything else
     */
    public function updateStatus(Request $request, $orderCode)
    {
        try {
            // ── auth ──────────────────────────────────────────
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Admin access required',
                ], 403);
            }

            // ── validate order_code ───────────────────────────────────
            $order = Order::where('order_code', $orderCode)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            // ── validate body ─────────────────────────────────
            $newStatus = $request->input('status');

            if (!$newStatus || !in_array($newStatus, self::VALID_STATUSES)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status. Allowed: ' . implode(', ', self::VALID_STATUSES),
                ], 422);
            }

            // ── terminal-state guard ──────────────────────────
            // Once an order is cancelled or completed it stays that way.
            if ($order->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a cancelled order.',
                ], 422);
            }

            if ($order->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a completed order.',
                ], 422);
            }

            // ── same-status guard ─────────────────────────────
            if ($order->status === $newStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is already in this status.',
                ], 422);
            }

            // ── persist ───────────────────────────────────────
            $oldStatus = $order->status;
            $order->status = $newStatus;
            $order->save();

            Log::info("Admin {$user->id} updated order {$order->order_code} status: {$oldStatus} → {$newStatus}");

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully.',
                'data'    => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status.',
            ], 500);
        }
    }
}