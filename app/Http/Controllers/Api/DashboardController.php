<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function adminIndex(Request $request)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Admin access required',
            ], 403);
        }

        $timeRange = $request->get('timeRange', 'week');
        $dateRange = $this->getDateRange($timeRange);

        // Total Revenue
        $totalRevenue = DB::table('orders')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'completed')
            ->sum('total_amount');

        // Total Orders
        $totalOrders = DB::table('orders')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        // New Customers
        $totalCustomers = DB::table('users')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        // Average Order Value
        $averageOrderValue = $totalOrders > 0
            ? $totalRevenue / $totalOrders
            : 0;

        // Order Status Breakdown
        $completed = DB::table('orders')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'completed')
            ->count();

        $pending = DB::table('orders')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'pending')
            ->count();

        $cancelled = DB::table('orders')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'cancelled')
            ->count();

        $orderStatusData = [
            'completed' => $completed,
            'pending' => $pending,
            'cancelled' => $cancelled,
        ];

        // Revenue Overview (Chart)
        $revenueData = DB::table('orders')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total')
            )
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top Products
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->select(
                'products.name',
                DB::raw('SUM(order_items.quantity) as orders'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
            )
            ->whereBetween('orders.created_at', [$dateRange['start'], $dateRange['end']])
            ->where('orders.status', 'completed')
            ->groupBy('products.name')
            ->orderByDesc('orders')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'keyMetrics' => [
                    'totalRevenue' => $totalRevenue,
                    'totalOrders' => $totalOrders,
                    'averageOrderValue' => round($averageOrderValue, 2),
                    'totalCustomers' => $totalCustomers,
                    'growthRate' => 12.5,
                ],
                'revenueData' => $revenueData,
                'orderStatusData' => $orderStatusData,
                'popularProducts' => $topProducts,
            ],
        ]);
    }

    public function userIndex(Request $request)
    {
        $user = $request->user();

        $totalOrders = DB::table('orders')
            ->where('user_id', $user->id)
            ->count();

        $totalSpent = DB::table('orders')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('total_amount');

        $pendingOrders = DB::table('orders')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'totalOrders' => $totalOrders,
                'totalSpent' => round($totalSpent, 2),
                'pendingOrders' => $pendingOrders,
            ],
        ]);
    }

    private function getDateRange($timeRange)
    {
        switch ($timeRange) {
            case 'today':
                return [
                    'start' => Carbon::today(),
                    'end' => Carbon::now(),
                ];

            case 'month':
                return [
                    'start' => Carbon::now()->startOfMonth(),
                    'end' => Carbon::now(),
                ];

            case 'year':
                return [
                    'start' => Carbon::now()->startOfYear(),
                    'end' => Carbon::now(),
                ];

            case 'week':
            default:
                return [
                    'start' => Carbon::now()->startOfWeek(),
                    'end' => Carbon::now(),
                ];
        }
    }
}
