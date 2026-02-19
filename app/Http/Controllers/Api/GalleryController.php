<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GalleryController extends Controller
{
    private $uploadFolder = 'gallery/images';

    /**
     * List all galleries
     */
    public function index()
    {
        $galleries = Gallery::all();
        
        $data = $galleries->map(function ($g) {
            return [
                'id' => $g->id,
                'title' => $g->title,
                'description' => $g->description,
                'image_url' => url($g->image_path),
                'created_at' => $g->created_at,
            ];
        });

        Log::info('[Gallery] Fetching galleries', ['count' => $data->count()]);

        return response()->json($data);
    }

    /**
     * Store a new gallery
     */
    public function store(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can add galleries.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|max:12288', // 12MB max
        ]);

        $imagePath = $this->saveImage($request->file('image'));

        $gallery = Gallery::create([
            'title' => $request->title,
            'description' => $request->description,
            'image_path' => $imagePath,
        ]);

        Log::info('[Gallery] Created new gallery', ['id' => $gallery->id, 'title' => $gallery->title]);

        return response()->json([
            'success' => true,
            'message' => 'Gallery created successfully',
            'data' => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'description' => $gallery->description,
                'image_url' => url($gallery->image_path),
                'created_at' => $gallery->created_at,
            ]
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can update galleries.'], 403);
        }

        $gallery = Gallery::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:12288',
        ]);

        // Update fields
        if ($request->has('title')) {
            $gallery->title = $request->title;
        }
        
        if ($request->has('description')) {
            $gallery->description = $request->description;
        }

        // Handle image update
        if ($request->hasFile('image')) {
            $this->deleteImage($gallery->image_path);
            $gallery->image_path = $this->saveImage($request->file('image'));
        }

        $gallery->save();

        Log::info('[Gallery] Updated gallery', ['id' => $gallery->id]);

        return response()->json([
            'success' => true,
            'message' => 'Gallery updated successfully',
            'data' => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'description' => $gallery->description,
                'image_url' => url($gallery->image_path),
                'created_at' => $gallery->created_at,
            ]
        ]);
    }

    public function destroy($id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can delete galleries.'], 403);
        }

        $gallery = Gallery::findOrFail($id);

        // Delete image file
        $this->deleteImage($gallery->image_path);

        $gallery->delete();

        Log::info('[Gallery] Deleted gallery', ['id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Gallery deleted successfully'
        ]);
    }

    /**
     * Save uploaded image to public/gallery/images
     */
    private function saveImage($file)
    {
        $folderPath = public_path($this->uploadFolder);

        if (! is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $filename = time().'_'.preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $file->move($folderPath, $filename);

        return $this->uploadFolder.'/'.$filename;
    }

    /**
     * Delete an image file safely
     */
    private function deleteImage($path)
    {
        $fullPath = public_path($path);
        if ($path && file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}