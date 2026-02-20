<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function analytics(): JsonResponse
    {
        try {
            // ── Key Metrics ───────────────────────────────────────────────
            $totalRevenue = DB::table('orders')
                ->whereNotIn('status', ['cancelled'])
                ->sum('total');

            $totalOrders = DB::table('orders')->count();

            $averageOrderValue = $totalOrders > 0
                ? round($totalRevenue / $totalOrders, 2)
                : 0;

            $totalCustomers = DB::table('orders')
                ->distinct('customer_name')
                ->count('customer_name');

            // Growth rate: compare this month vs last month
            $thisMonthRevenue = DB::table('orders')
                ->whereNotIn('status', ['cancelled'])
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('total');

            $lastMonthRevenue = DB::table('orders')
                ->whereNotIn('status', ['cancelled'])
                ->whereMonth('created_at', Carbon::now()->subMonth()->month)
                ->whereYear('created_at', Carbon::now()->subMonth()->year)
                ->sum('total');

            $growthRate = $lastMonthRevenue > 0
                ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                : 0;

            // ── Revenue Trends (last 30 days) ─────────────────────────────
            $revenueData = DB::table('orders')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->whereNotIn('status', ['cancelled'])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => Carbon::parse($row->date)->format('M d'),
                    'revenue' => (float) $row->revenue,
                    'orders' => (int) $row->orders,
                ]);

            // ── Order Status Distribution ─────────────────────────────────
            $statusCounts = DB::table('orders')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get();

            $orderStatusData = $statusCounts->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
                'percentage' => $totalOrders > 0
                    ? round(($row->count / $totalOrders) * 100, 1)
                    : 0,
            ]);

            // ── Payment Method Breakdown ──────────────────────────────────
            $paymentCounts = DB::table('orders')
                ->select('payment_method as method', DB::raw('COUNT(*) as count'))
                ->whereNotNull('payment_method')
                ->groupBy('payment_method')
                ->get();

            $paymentMethodData = $paymentCounts->map(fn ($row) => [
                'method' => $row->method,
                'count' => (int) $row->count,
                'percentage' => $totalOrders > 0
                    ? round(($row->count / $totalOrders) * 100, 1)
                    : 0,
            ]);

            // ── Popular Products ──────────────────────────────────────────
            $popularProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereNotIn('orders.status', ['cancelled'])
                ->select(
                    'products.id',
                    'products.name',
                    DB::raw('SUM(order_items.quantity) as total_quantity'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
                )
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_quantity')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'orders' => (int) $row->total_quantity,
                    'revenue' => (float) $row->total_revenue,
                ]);

            
            // ── Products Count ────────────────────────────────────────────
            $productsCount = DB::table('products')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'keyMetrics' => [
                        'totalRevenue' => (float) $totalRevenue,
                        'totalOrders' => (int) $totalOrders,
                        'averageOrderValue' => (float) $averageOrderValue,
                        'totalCustomers' => (int) $totalCustomers,
                        'growthRate' => $growthRate,
                    ],
                    'revenueData' => $revenueData,
                    'orderStatusData' => $orderStatusData,
                    'paymentMethodData' => $paymentMethodData,
                    'popularProducts' => $popularProducts,
                    'productsCount' => (int) $productsCount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics: '.$e->getMessage(),
            ], 500);
        }
    }
}
