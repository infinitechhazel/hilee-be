<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function analytics(): JsonResponse
    {
        try {
            // ── Key Metrics ───────────────────────────────────────────────
            $totalRevenue = DB::table('orders')
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');

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
                ->sum('total_amount');

            $lastMonthRevenue = DB::table('orders')
                ->whereNotIn('status', ['cancelled'])
                ->whereMonth('created_at', Carbon::now()->subMonth()->month)
                ->whereYear('created_at', Carbon::now()->subMonth()->year)
                ->sum('total_amount');

            $growthRate = $lastMonthRevenue > 0
                ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                : 0;

            // ── Revenue Trends (last 30 days) ─────────────────────────────
            $revenueData = DB::table('orders')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->whereNotIn('status', ['cancelled'])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn($row) => [
                    'date'    => Carbon::parse($row->date)->format('M d'),
                    'revenue' => (float) $row->revenue,
                    'orders'  => (int) $row->orders,
                ]);

            // ── Order Status Distribution ─────────────────────────────────
            $statusCounts = DB::table('orders')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get();

            $orderStatusData = $statusCounts->map(fn($row) => [
                'status'     => $row->status,
                'count'      => (int) $row->count,
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

            $paymentMethodData = $paymentCounts->map(fn($row) => [
                'method'     => $row->method,
                'count'      => (int) $row->count,
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
                    'products.name',
                    'products.category',
                    DB::raw('COALESCE(products.is_spicy, 0) as is_spicy'),
                    DB::raw('SUM(order_items.quantity) as orders'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
                )
                ->groupBy('products.id', 'products.name', 'products.category', 'products.is_spicy')
                ->orderByDesc('orders')
                ->limit(10)
                ->get()
                ->map(fn($row) => [
                    'name'     => $row->name,
                    'category' => $row->category,
                    'is_spicy' => (bool) $row->is_spicy,
                    'orders'   => (int) $row->orders,
                    'revenue'  => (float) $row->revenue,
                ]);

            // ── Category Performance ──────────────────────────────────────
            $categoryData = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereNotIn('orders.status', ['cancelled'])
                ->select(
                    'products.category',
                    DB::raw('SUM(order_items.quantity) as orders'),
                    DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
                )
                ->groupBy('products.category')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn($row) => [
                    'category' => $row->category,
                    'orders'   => (int) $row->orders,
                    'revenue'  => (float) $row->revenue,
                ]);

            // ── Products Count ────────────────────────────────────────────
            $productsCount = DB::table('products')->count();

            return response()->json([
                'success' => true,
                'data'    => [
                    'keyMetrics' => [
                        'totalRevenue'      => (float) $totalRevenue,
                        'totalOrders'       => (int)   $totalOrders,
                        'averageOrderValue' => (float) $averageOrderValue,
                        'totalCustomers'    => (int)   $totalCustomers,
                        'growthRate'        => $growthRate,
                    ],
                    'revenueData'       => $revenueData,
                    'orderStatusData'   => $orderStatusData,
                    'paymentMethodData' => $paymentMethodData,
                    'popularProducts'   => $popularProducts,
                    'categoryData'      => $categoryData,
                    'productsCount'     => (int) $productsCount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics: ' . $e->getMessage(),
            ], 500);
        }
    }
}