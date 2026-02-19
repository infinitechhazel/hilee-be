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
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }


            // Validate the request
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string',
                'order_code' => 'string|unique:orders,order_code',
                'proof_of_payment' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'notes' => 'nullable|string',
                'total_amount' => 'required|numeric|min:0',
                'items' => 'required|string', // JSON string of items
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Parse items JSON
            $items = json_decode($request->input('items'), true);

            if (!$items || !is_array($items) || count($items) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid items data',
                ], 422);
            }

            $proofOfPaymentPath = null;
            if ($request->hasFile('proof_of_payment')) {
                $file = $request->file('proof_of_payment');

                // Generate unique filename
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                // Ensure the directory exists
                $directory = 'proof_of_payments';
                $fullPath = public_path('images/' . $directory);

                if (!file_exists($fullPath)) {
                    mkdir($fullPath, 0755, true);
                }

                // Move file to public folder (NOT using storeAs!)
                $file->move($fullPath, $filename);

                // Store relative path for database
                $proofOfPaymentPath = $directory . '/' . $filename;

                Log::info('Proof of payment stored at: ' . $fullPath . '/' . $filename);
            }

            // Start database transaction
            DB::beginTransaction();

            // Generate a unique order code 
            $orderCode = 'ORD' . now()->format('Ymd') . '' . Str::upper(Str::random(6));

            try {
                // Create the order
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_code' => $orderCode,
                    'total_price' => $request->input('total_amount'),
                    'status' => 'pending',
                    'payment_method' => $request->input('payment_method'),
                    'proof_of_payment' => $proofOfPaymentPath,
                    'notes' => $request->input('notes'),
                    'ordered_at' => now(),
                ]);

                // Create order items and update product stock
                foreach ($items as $item) {
                    // Verify product exists and has enough stock
                    $product = Product::find($item['id']);

                    if (!$product) {
                        throw new \Exception("Product not found: " . $item['name']);
                    }

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: " . $product->name);
                    }

                    // Create order item
                    OrderItem::create([
                        'order_id' => $order->id,
                        'order_code' => $orderCode,
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'subtotal' => $item['subtotal'],
                    ]);

                    // Update product stock
                    $product->decrement('stock', $item['quantity']);

                    // Remove from cart
                    Cart::where('user_id', $user->id)
                        ->where('product_id', $product->id)
                        ->delete();
                }

                // Commit the transaction
                DB::commit();

                // Load the order with relationships
                $order->load(['orderItems.product']);

                return response()->json([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'data' => $order,
                ], 201);
            } catch (\Exception $e) {
                // Rollback the transaction
                DB::rollBack();

                // Delete uploaded file if transaction failed
                if ($proofOfPaymentPath) {
                    Storage::disk('public')->delete($proofOfPaymentPath);
                }

                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error creating order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
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
                'status' => 'required|string|in:pending,confirmed,processing,shipped,completed,cancelled',
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
    public function cancel(Request $request, $orderCode)
    {
        try {
            // Log the incoming request
            Log::info('Cancel order request received', [
                'order_code' => $orderCode,
                'user_id' => $request->user()?->id,
            ]);

            $user = $request->user();

            if (!$user) {
                Log::warning('Unauthorized cancel attempt', [
                    'order_code' => $orderCode,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Log the query attempt
            Log::info('Searching for order', [
                'order_code' => $orderCode,
                'user_id' => $user->id,
            ]);

            $order = Order::where('order_code', $orderCode)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                Log::warning('Order not found', [
                    'order_code' => $orderCode,
                    'user_id' => $user->id,
                ]);

                // Additional debug: Check if order exists at all
                $orderExists = Order::where('order_code', $orderCode)->exists();
                Log::info('Order exists in database?', [
                    'order_code' => $orderCode,
                    'exists' => $orderExists,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            Log::info('Order found', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'status' => $order->status,
                'user_id' => $order->user_id,
            ]);

            if ($order->status !== 'pending') {
                Log::warning('Cannot cancel order - invalid status', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'current_status' => $order->status,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be cancelled',
                ], 400);
            }

            DB::beginTransaction();

            try {
                Log::info('Starting order cancellation process', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                ]);

                // Restore product stock
                foreach ($order->orderItems as $orderItem) {
                    Log::info('Restoring stock for product', [
                        'product_id' => $orderItem->product_id,
                        'quantity' => $orderItem->quantity,
                    ]);

                    $product = Product::find($orderItem->product_id);
                    if ($product) {
                        $oldStock = $product->stock;
                        $product->increment('stock', $orderItem->quantity);
                        Log::info('Stock restored', [
                            'product_id' => $product->id,
                            'old_stock' => $oldStock,
                            'new_stock' => $product->stock,
                            'restored_quantity' => $orderItem->quantity,
                        ]);
                    } else {
                        Log::warning('Product not found for stock restoration', [
                            'product_id' => $orderItem->product_id,
                        ]);
                    }
                }

                // Update order status
                $order->update([
                    'status' => 'cancelled',
                ]);

                Log::info('Order status updated to cancelled', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                ]);

                DB::commit();

                Log::info('Order cancellation completed successfully', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                ]);

                $order->load(['orderItems.product']);

                return response()->json([
                    'success' => true,
                    'message' => 'Order cancelled successfully',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error during order cancellation transaction', [
                    'order_code' => $orderCode,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error cancelling order', [
                'order_code' => $orderCode,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Handle image upload and return the path
     */
    private function handleImageUpload($file)
    {
        try {
            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            // Define upload path (public/images/products)
            $uploadPath = public_path('images/proof_of_payments');

            // Create directory if it doesn't exist
            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }

            // Move file to public directory
            $file->move($uploadPath, $filename);

            // Return relative path
            return 'images/products/' . $filename;
        } catch (\Exception $e) {
            Log::error('Error uploading product image', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


}