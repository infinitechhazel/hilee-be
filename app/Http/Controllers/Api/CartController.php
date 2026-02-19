<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Get all cart items for the authenticated user
     */

    public function index()
    {
        try {
            Log::info('=== CART CONTROLLER DEBUG ===');

            // Log all headers
            $headers = request()->headers->all();
            Log::info('Request headers:', $headers);

            // Check if Authorization header exists
            $authHeader = request()->header('Authorization');
            Log::info('Authorization header:', ['header' => $authHeader]);

            // Try to get user
            $user = Auth::guard('sanctum')->user();

            Log::info('Auth check:', [
                'guard_check' => Auth::guard('sanctum')->check(),
                'user_exists' => $user !== null,
                'user_id' => $user ? $user->id : 'NULL',
                'user_email' => $user ? $user->email : 'NULL'
            ]);

            if (!$user) {
                Log::error('Cart access: User not authenticated', [
                    'has_token' => !empty($authHeader),
                    'guard' => 'sanctum'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not found. Please log in again.',
                    'debug' => [
                        'has_auth_header' => !empty($authHeader),
                        'guard_used' => 'sanctum'
                    ]
                ], 401);
            }

            Log::info('Fetching cart for user', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            $cartItems = Cart::where('user_id', $user->id)
                ->with([
                    'product' => function ($query) {
                        $query->select('id', 'name', 'description', 'category', 'price', 'stock', 'image_url', 'is_active');
                    }
                ])
                ->get()
                ->map(function ($item) {
                    if (!$item->product) {
                        return null;
                    }

                    return [
                        'id' => $item->product_id,
                        'name' => $item->product->name,
                        'description' => $item->product->description,
                        'category' => $item->product->category,
                        'price' => (float) $item->product->price,
                        'stock' => $item->product->stock,
                        'image_url' => $item->product->image_url,
                        'quantity' => $item->quantity,
                        'subtotal' => (float) ($item->product->price * $item->quantity),
                    ];
                })
                ->filter()
                ->values();

            Log::info('Cart items fetched successfully', [
                'count' => $cartItems->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $cartItems,
                'total' => $cartItems->sum('subtotal'),
                'count' => $cartItems->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching cart items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        try {
            if (!$user = auth('sanctum')->user()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $product = Product::findOrFail($request->product_id);

            // Check if product is active
            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is not available',
                ], 400);
            }

            // Check stock availability
            if ($request->quantity > $product->stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock available',
                ], 400);
            }

            // Check if item already exists in cart
            $cartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($cartItem) {
                // Update quantity if item exists
                $newQuantity = $cartItem->quantity + $request->quantity;

                if ($newQuantity > $product->stock) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total quantity exceeds available stock',
                    ], 400);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->save();
            } else {
                // Create new cart item
                $cartItem = Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Item added to cart successfully',
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $cartItem->quantity,
                    'price' => $product->price,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding to cart', [
                'error' => $e->getMessage(),
                'product_id' => $request->product_id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to cart',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    /**
     * Update cart item quantity
     */
    public function update(Request $request, $product)
    {
        try {
            if (!$user = auth('sanctum')->user()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Log::info('Update cart request', [
                'user_id' => $user->id,
                'product_id' => $product,
                'requested_quantity' => $request->quantity
            ]);

            $productModel = Product::findOrFail($product);

            // Check stock availability
            if ($request->quantity > $productModel->stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock available',
                ], 400);
            }

            $cartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $product)
                ->first();
            Log::info('Attempting to remove cart item', [
                'user_id' => $user->id,
                'product_id' => $product,
                'product_type' => gettype($product)
            ]);
            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in cart',
                ], 404);
            }

            $cartItem->quantity = $request->quantity;
            $cartItem->save();

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'data' => [
                    'id' => $productModel->id,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $productModel->price * $cartItem->quantity,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating cart item', [
                'error' => $e->getMessage(),
                'product_id' => $product ?? 'undefined',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function destroy($product)
    {
        try {
            if (!auth('sanctum')->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }

            $user = auth('sanctum')->user();

            Log::info('Attempting to remove cart item', [
                'user_id' => $user->id,
                'product_id' => $product,
                'product_type' => gettype($product)
            ]);

            $cartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $product)
                ->first();

            if (!$cartItem) {
                Log::warning('Cart item not found', [
                    'user_id' => $user->id,
                    'product_id' => $product,
                    'all_cart_items' => Cart::where('user_id', $user->id)->pluck('product_id')->toArray()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in cart',
                ], 404);
            }

            $cartItem->delete();

            Log::info('Cart item removed successfully', [
                'user_id' => $user->id,
                'product_id' => $product
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing cart item', [
                'error' => $e->getMessage(),
                'product_id' => $product ?? 'undefined',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from cart',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear entire cart
     */
    public function clear()
    {
        try {
            if (!auth('sanctum')->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }

            $user = auth('sanctum')->user();


            Cart::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error clearing cart', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cart item count
     */
    public function count()
    {
        try {
            if (!auth('sanctum')->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please log in.',
                ], 401);
            }

            $user = auth('sanctum')->user();


            $count = Cart::where('user_id', $user->id)->sum('quantity');

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting cart count', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get cart count',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}