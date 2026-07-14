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
    public function index(Request $request)
    {
        try {
            $query = Product::query();

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->has('status') && $request->status !== 'all') {
                $query->where('is_active', $request->status === 'active');
            }

            if ($request->has('stock_status') && $request->stock_status !== 'all') {
                if ($request->stock_status === 'in_stock') {
                    $query->where('stock', '>', 0);
                } elseif ($request->stock_status === 'out_of_stock') {
                    $query->where('stock', 0);
                } elseif ($request->stock_status === 'low_stock') {
                    $query->where('stock', '>', 0)->where('stock', '<', 10);
                }
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            if ($request->get('paginate') === 'false') {
                return response()->json($query->get());
            }

            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'description' => 'nullable|string',
                'price'       => 'required|numeric|min:0',
                'stock'       => 'required|integer|min:0',
                'category'    => 'nullable|string|max:100',
                'is_active'   => 'nullable|boolean',
                'image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:10240',
                'tiktok_url'  => 'nullable|string',
                'shopee_url'  => 'nullable|string',
                'lazada_url'  => 'nullable|string',
            ]);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $this->handleImageUpload($request->file('image'));
            }

            $product = Product::create([
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price'       => $validated['price'],
                'stock'       => $validated['stock'],
                'category'    => !empty($validated['category']) ? $validated['category'] : null,
                'is_active'   => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
                'image'       => $imagePath,
                'tiktok_url'  => !empty($validated['tiktok_url']) ? $validated['tiktok_url'] : null,
                'shopee_url'  => !empty($validated['shopee_url']) ? $validated['shopee_url'] : null,
                'lazada_url'  => !empty($validated['lazada_url']) ? $validated['lazada_url'] : null,
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

    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validated = $request->validate([
                'name'        => 'sometimes|required|string|max:255',
                'description' => 'sometimes|nullable|string',
                'price'       => 'sometimes|required|numeric|min:0',
                'stock'       => 'sometimes|required|integer|min:0',
                'category'    => 'sometimes|nullable|string|max:100',
                'is_active'   => 'sometimes|nullable|boolean',
                'image'       => 'sometimes|nullable|image|mimes:jpeg,jpg,png,gif,webp|max:10240',
                'tiktok_url'  => 'sometimes|nullable|string',
                'shopee_url'  => 'sometimes|nullable|string',
                'lazada_url'  => 'sometimes|nullable|string',
            ]);

            if ($request->hasFile('image')) {
                if ($product->image) {
                    $this->deleteImage($product->image);
                }
                $validated['image'] = $this->handleImageUpload($request->file('image'));
            }

            $updates = [];

            if (array_key_exists('name', $validated))
                $updates['name'] = $validated['name'];

            if (array_key_exists('description', $validated))
                $updates['description'] = $validated['description'];

            if (array_key_exists('price', $validated))
                $updates['price'] = $validated['price'];

            if (array_key_exists('stock', $validated))
                $updates['stock'] = $validated['stock'];

            if (array_key_exists('category', $validated))
                $updates['category'] = !empty($validated['category']) ? $validated['category'] : null;

            if (array_key_exists('image', $validated))
                $updates['image'] = $validated['image'];

            if ($request->has('is_active')) {
                $updates['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $product->is_active;
            }

            foreach (['tiktok_url', 'shopee_url', 'lazada_url'] as $urlField) {
                if (array_key_exists($urlField, $validated)) {
                    $updates[$urlField] = !empty($validated[$urlField]) ? $validated[$urlField] : null;
                }
            }

            $product->update($updates);

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

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            if ($product->image) {
                $this->deleteImage($product->image);
            }

            $product->delete();

            return response()->json(null, 204);

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