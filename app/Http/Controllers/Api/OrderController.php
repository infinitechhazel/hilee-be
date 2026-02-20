<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Get all orders for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $orders = Order::with(['orderItems.product'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific order by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $order = Order::with(['orderItems.product'])
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new order
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $validator = Validator::make($request->all(), [
                'items' => 'array|min:1',
                'items.*.id' => 'nullable',           // id can be null for guest items
                'items.*.price' => 'numeric',
                'items.*.stock' => 'integer|min:1',
                'customer_name' => 'string',
                'customer_email' => 'email',
                'customer_phone' => 'string',
                'payment_method' => 'string|in:cash,gcash,security_bank',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get items - handle both JSON body and form data
            $items = $request->input('items');
            if (is_string($items)) {
                $items = json_decode($items, true);
            }

            Log::info('Items received', ['items' => $request->items]);

            if (!$items || !is_array($items) || count($items) === 0) {
                return response()->json(['success' => false, 'message' => 'Invalid items data'], 422);
            }

            // Handle receipt_file - base64 from JSON body
            $receiptFilePath = null;
            $receiptFile = $request->input('receipt_file');

            if ($receiptFile && str_starts_with($receiptFile, 'data:image')) {
                $image = explode(',', $receiptFile);
                $imageData = base64_decode($image[1]);

                preg_match('/data:image\/(\w+);/', $receiptFile, $matches);
                $ext = $matches[1] ?? 'jpg';

                $filename = time() . '_' . uniqid() . '.' . $ext;
                $directory = public_path('images/proof_of_payments');

                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                file_put_contents($directory . '/' . $filename, $imageData);
                $receiptFilePath = $filename; // just the filename; URL built in model accessor
            }

            DB::beginTransaction();

            // Generate unique order codes
            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
            $orderCode = 'ORD' . now()->format('Ymd') . Str::upper(Str::random(6));

            // Calculate totals from items
            $subtotal = collect($items)->sum(fn($item) => $item['price'] * $item['quantity']);
            $total = $subtotal; // adjust if you have delivery fees/tax

            try {
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_number' => $orderNumber,
                    'order_code' => $orderCode,
                    'customer_name' => $request->input('customer_name'),
                    'customer_email' => $request->input('customer_email'),
                    'customer_phone' => $request->input('customer_phone'),
                    'delivery_address' => $request->input('delivery_address'),
                    'delivery_city' => $request->input('delivery_city'),
                    'delivery_zip_code' => $request->input('delivery_zip_code'),
                    'payment_method' => $request->input('payment_method', 'cash'),
                    'payment_status' => 'pending',
                    'receipt_file' => $receiptFilePath,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'notes' => $request->input('notes'),
                ]);

                foreach ($items as $item) {
                    $product = isset($item['id']) ? Product::find($item['id']) : null;

                    if (!$product) {
                        // Guest/no-product-match: store item data directly
                        OrderItem::create([
                            'order_id' => $order->id,
                            'order_code' => $orderCode,
                            'product_id' => null,
                            'name' => $item['name'],
                            'quantity' => $item['quantity'],   // 'quantity' not 'stock'
                            'price' => $item['price'],
                            'subtotal' => $item['price'] * $item['quantity'],
                        ]);
                        continue;
                    }


                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for: " . $product->name);
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'order_code' => $orderCode,
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],   // 'quantity' not 'stock'
                        'price' => $item['price'],
                        'subtotal' => $item['price'] * $item['quantity'],
                    ]);


                    $product->decrement('stock', $item['quantity']);


                }

                DB::commit();

                $order->load(['orderItems.product']);

                return response()->json([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'data' => $order,
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                if ($receiptFilePath) {
                    @unlink(public_path('images/proof_of_payments/' . $receiptFilePath));
                }
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error creating order: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update order status (admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Admin access required',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,confirmed,preparing,ready,delivered,cancelled',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Use the $id parameter instead of order_code from request
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            $order->update([
                'status' => $request->input('status'),
            ]);

            $order->load(['orderItems.product']);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating order status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel an order (user can only cancel pending orders)
     */
    public function cancel(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Find by ID and ensure it belongs to the user
            $order = Order::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            if (!in_array($order->status, ['pending', 'confirmed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending or confirmed orders can be cancelled',
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Restore product stock
                foreach ($order->orderItems as $orderItem) {
                    $product = Product::find($orderItem->product_id);
                    if ($product) {
                        $product->increment('stock', $orderItem->quantity);
                    }
                }

                $order->update(['status' => 'cancelled']);

                DB::commit();

                $order->load(['orderItems.product']);

                return response()->json([
                    'success' => true,
                    'message' => 'Order cancelled successfully',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error cancelling order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



}