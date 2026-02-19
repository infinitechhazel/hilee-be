<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MerchandizeController extends Controller
{
    /**
     * Display a listing of merchandise with filtering, sorting, and pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
  

    public function index(Request $request)
    {
        try {
            // 🔍 Log incoming request
            Log::info('Merchandize index called', [
                'query_params' => $request->all(),
            ]);

            $query = Product::query();

            // Filter by status
            if ($request->has('status')) {
                Log::info('Applying status filter', ['status' => $request->status]);

                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            // Filter by category
            if ($request->has('category') && $request->category !== 'all') {
                Log::info('Applying category filter', ['category' => $request->category]);
                $query->where('category', $request->category);
            }

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                Log::info('Applying search filter', ['search' => $request->search]);

                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Stock filter
            if ($request->has('in_stock')) {
                Log::info('Applying stock filter', ['in_stock' => $request->in_stock]);

                if ($request->in_stock === 'true') {
                    $query->where('stock', '>', 0);
                } elseif ($request->in_stock === 'false') {
                    $query->where('stock', '=', 0);
                }
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = strtolower($request->get('sort_order', 'desc'));

            $allowedSortFields = ['name', 'price', 'stock', 'created_at', 'category'];
            if (!in_array($sortBy, $allowedSortFields)) {
                Log::warning('Invalid sort_by provided, falling back', ['sort_by' => $sortBy]);
                $sortBy = 'created_at';
            }

            $sortOrder = $sortOrder === 'asc' ? 'asc' : 'desc';

            Log::info('Applying sorting', [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]);

            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min(max((int) $request->get('per_page', 12), 1), 100);

            Log::info('Pagination settings', ['per_page' => $perPage]);

            // 🔎 Log SQL BEFORE execution
            Log::debug('Final merchandize SQL', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $merchandize = $query->paginate($perPage);

            // 📊 Log result summary
            Log::info('Merchandize query result', [
                'total' => $merchandize->total(),
                'count' => $merchandize->count(),
                'current_page' => $merchandize->currentPage(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Merchandise retrieved successfully',
                'data' => $merchandize,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Merchandize index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchandise',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Store a newly created merchandise item.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('merchandize', 'public');
                $data['image_url'] = '/storage/' . $imagePath;
            }

            $merchandize = Merchandize::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Merchandise created successfully',
                'data' => $merchandize,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create merchandise',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified merchandise item.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $merchandize = Merchandize::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Merchandise retrieved successfully',
                'data' => $merchandize,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchandise not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve merchandise',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified merchandise item.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $merchandize = Merchandize::findOrFail($id);
            $data = $validator->validated();

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($merchandize->image_url) {
                    $oldImagePath = str_replace('/storage/', '', $merchandize->image_url);
                    Storage::disk('public')->delete($oldImagePath);
                }

                $image = $request->file('image');
                $imagePath = $image->store('merchandize', 'public');
                $data['image_url'] = '/storage/' . $imagePath;
            }

            $merchandize->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Merchandise updated successfully',
                'data' => $merchandize,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchandise not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update merchandise',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified merchandise item.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $merchandize = Merchandize::findOrFail($id);

            // Delete associated image
            if ($merchandize->image_url) {
                $imagePath = str_replace('/storage/', '', $merchandize->image_url);
                Storage::disk('public')->delete($imagePath);
            }

            $merchandize->delete();

            return response()->json([
                'success' => true,
                'message' => 'Merchandise deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchandise not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete merchandise',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update stock quantity for a merchandise item.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $merchandize = Merchandize::findOrFail($id);
            $merchandize->update(['stock' => $request->stock]);

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => $merchandize,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchandise not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active status of merchandise item.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($id)
    {
        try {
            $merchandize = Merchandize::findOrFail($id);
            $merchandize->update(['is_active' => !$merchandize->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Merchandise status updated successfully',
                'data' => $merchandize,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Merchandise not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get merchandise categories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories()
    {
        try {
            $categories = Merchandize::select('category')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->pluck('category');

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => $categories,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}