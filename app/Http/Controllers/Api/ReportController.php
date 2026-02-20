<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Return report data for a given time range.
     */
    public function index(Request $request)
    {
        $range = $request->query('range', 'week'); // today, week, month, year

        // Determine start date based on range
        $startDate = match($range) {
            'today' => now()->startOfDay(),
            'week'  => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year'  => now()->startOfYear(),
            default => now()->startOfWeek(),
        };

        $endDate = now();

        // ── Summary ─────────────────────────────
        $summary = DB::table('orders')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as total_orders, SUM(total_amount) as total_revenue')
            ->first();

        $newCustomers = DB::table('users')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $avgOrderValue = $summary->total_orders > 0
            ? $summary->total_revenue / $summary->total_orders
            : 0;

        // ── Revenue Chart ───────────────────────
        $revenueChart = DB::table('orders')
            ->selectRaw('DATE(created_at) as period, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // ── Top Products ────────────────────────
        $topProducts = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('products.id as product_id, products.name, SUM(order_items.qty) as total_qty, SUM(order_items.price * order_items.qty) as total_revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // ── Order Status Stats ─────────────────
        $statuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled'];
        $orderStats = [];
        $totalOrders = 0;
        foreach ($statuses as $status) {
            $count = DB::table('orders')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', $status)
                ->count();
            $orderStats[$status] = [
                'count' => $count,
                'rate'  => 0, // will calculate below
            ];
            $totalOrders += $count;
        }

        foreach ($statuses as $status) {
            $orderStats[$status]['rate'] = $totalOrders > 0
                ? round(($orderStats[$status]['count'] / $totalOrders) * 100, 2)
                : 0;
        }
        $orderStats['total'] = $totalOrders;

        // ── Payment Breakdown ──────────────────
        $paymentRows = DB::table('orders')
            ->selectRaw('payment_method as method, payment_status, COUNT(*) as count, SUM(total_amount) as revenue')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_method', 'payment_status')
            ->get();

        // ── Response ───────────────────────────
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_revenue'   => $summary->total_revenue ?? 0,
                    'total_orders'    => $summary->total_orders ?? 0,
                    'new_customers'   => $newCustomers,
                    'avg_order_value' => $avgOrderValue,
                ],
                'revenue_chart'     => $revenueChart,
                'top_products'      => $topProducts,
                'order_stats'       => $orderStats,
                'payment_breakdown' => $paymentRows,
            ],
        ]);
    }
}