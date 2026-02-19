<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products with filters and pagination
     */
    public function index(Request $request)
    {
        try {
            $query = Product::query();

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Status filter (active/inactive)
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('is_active', $request->status === 'active');
            }

            // Stock filter
            if ($request->has('stock_status') && $request->stock_status !== 'all') {
                if ($request->stock_status === 'in_stock') {
                    $query->where('quantity', '>', 0);
                } elseif ($request->stock_status === 'out_of_stock') {
                    $query->where('quantity', 0);
                } elseif ($request->stock_status === 'low_stock') {
                    $query->where('quantity', '>', 0)->where('quantity', '<', 10);
                }
            }

            // Sorting
            $sortBy    = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Return all without pagination if requested
            if ($request->get('paginate') === 'false') {
                return response()->json($query->get());
            }

            // Pagination
            $perPage  = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $products,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'description' => 'nullable|string',
                'price'       => 'required|numeric|min:0',
                'quantity'    => 'required|integer|min:0',
                'is_active'   => 'nullable|boolean',
                'image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:10240',
            ]);

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $this->handleImageUpload($request->file('image'));
            }

            // Create product
            $product = Product::create([
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price'       => $validated['price'],
                'quantity'    => $validated['quantity'],
                'is_active'   => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
                'image'       => $imagePath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data'    => $product,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating product', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified product
     */
    public function show($id)
    {
        try {
            $product = Product::findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => $product,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching product', [
                'product_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'description' => 'nullable|string',
                'price'       => 'required|numeric|min:0',
                'quantity'    => 'required|integer|min:0',
                'is_active'   => 'nullable|boolean',
                'image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:10240',
            ]);

            // Handle image upload
            if ($request->hasFile('image')) {
                if ($product->image) {
                    $this->deleteImage($product->image);
                }
                $validated['image'] = $this->handleImageUpload($request->file('image'));
            }

            // Update product
            $product->update([
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price'       => $validated['price'],
                'quantity'    => $validated['quantity'],
                'is_active'   => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $product->is_active,
                'image'       => $validated['image'] ?? $product->image,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data'    => $product->fresh(),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating product', [
                'product_id' => $id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified product
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Delete image if exists
            if ($product->image) {
                $this->deleteImage($product->image);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting product', [
                'product_id' => $id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle image upload and return the path
     */
    private function handleImageUpload($file): string
    {
        try {
            $filename   = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $uploadPath = public_path('images/products');

            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }

            $file->move($uploadPath, $filename);

            return 'images/products/' . $filename;
        } catch (\Exception $e) {
            Log::error('Error uploading product image', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete image from filesystem
     */
    private function deleteImage(string $imagePath): void
    {
        try {
            $fullPath = public_path($imagePath);

            if (File::exists($fullPath)) {
                File::delete($fullPath);
                Log::info('Product image deleted', ['path' => $imagePath]);
            }
        } catch (\Exception $e) {
            Log::error('Error deleting product image', [
                'path'  => $imagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}