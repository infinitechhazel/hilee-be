<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    /**
     * List announcements
     */
    /**
 * List published/active announcements (public endpoint)
 */
public function published(Request $request)
{
    $query = Announcement::where('is_active', true);

    if ($request->filled('category')) {
        $query->where('category', $request->category);
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    $query->orderBy(
        $request->get('sort_by', 'priority'),
        $request->get('sort_order', 'desc')
    )->orderBy('created_at', 'desc');

    return response()->json([
        'success' => true,
        'data' => $query->paginate($request->get('per_page', 15)),
    ]);
}
    public function index(Request $request)
    {
        $query = Announcement::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderBy(
            $request->get('sort_by', 'created_at'),
            $request->get('sort_order', 'desc')
        );

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->get('per_page', 15)),
        ]);
    }

    /**
     * Store announcement
     */
    public function store(Request $request)
    {
        Log::info('ANNOUNCEMENT STORE HIT');
        Log::info('Request data:', $request->all());
        Log::info('Files:', $request->allFiles());

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'category' => 'required|string|in:Update,Event,Alert,Development,Health,Notice',
            'description' => 'required|string|max:500',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240', // 10MB
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            
            // Create directory if it doesn't exist
            $uploadPath = public_path('uploads/announcements');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $image->move($uploadPath, $filename);
            $data['image_url'] = 'uploads/announcements/' . $filename;
            
            Log::info('Image uploaded:', ['path' => $data['image_url']]);
        }

        $announcement = Announcement::create($data);

        Log::info('Announcement created:', ['id' => $announcement->id]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement created successfully',
            'data' => $announcement,
        ], 201);
    }

    /**
     * Show single announcement
     */
    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => Announcement::findOrFail($id),
        ]);
    }

    /**
     * Update announcement
     */
    public function update(Request $request, $id)
    {
        Log::info('ANNOUNCEMENT UPDATE HIT', ['id' => $id]);
        Log::info('Request data:', $request->all());
        
        $announcement = Announcement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
            'category' => 'sometimes|required|string|in:Update,Event,Alert,Development,Health,Notice',
            'description' => 'sometimes|required|string|max:500',
            'content' => 'sometimes|required|string',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240',
        ]);

        // if ($validator->fails()) {
        //     Log::error('Validation failed:', $validator->errors()->toArray());
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Validation failed',
        //         'errors' => $validator->errors(),
        //     ], 422);
        // }

        $data = $validator->validated();

        // Replace image if new one uploaded
        if ($request->hasFile('image')) {
            // Delete old image
            if ($announcement->image_url && file_exists(public_path($announcement->image_url))) {
                unlink(public_path($announcement->image_url));
            }

            $image = $request->file('image');
            $filename = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            
            $uploadPath = public_path('uploads/announcements');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $image->move($uploadPath, $filename);
            $data['image_url'] = 'uploads/announcements/' . $filename;
            
            Log::info('Image updated:', ['path' => $data['image_url']]);
        }

        $announcement->update($data);

        Log::info('Announcement updated:', ['id' => $announcement->id]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement updated successfully',
            'data' => $announcement->fresh(),
        ]);
    }

    /**
     * Delete announcement
     */
    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);

        // Delete image file
        if ($announcement->image_url && file_exists(public_path($announcement->image_url))) {
            unlink(public_path($announcement->image_url));
        }

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully',
        ]);
    }
}