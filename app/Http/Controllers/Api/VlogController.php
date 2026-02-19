<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VlogController extends Controller
{
    // Public list
    public function index()
    {
        $vlogs = Vlog::where('is_active', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $vlogs,
        ]);
    }

    // Admin-only list
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can view vlogs.'], 403);
        }

        $perPage = $request->integer('per_page', 10);

        $vlogs = Vlog::query()
            ->search($request->search)
            ->when($request->category, function ($q) use ($request) {
                return $q->where('category', $request->category);
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vlogs,
        ]);
    }

    // Chunked video upload
    public function uploadChunk(Request $request, $vlogId = null)
    {
        $admin = $request->user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
            'filename' => 'required|string',
        ]);

        $chunkIndex = (int) $request->chunk_index;
        $totalChunks = (int) $request->total_chunks;
        $filename = $request->filename;

        // Use filename + user ID for consistent hash across all chunks
        $userId = $admin->id;
        $uniqueId = $vlogId ? "edit_{$vlogId}_{$userId}" : "new_{$userId}";
        $hash = md5($filename . $uniqueId);
        $tmpDir = storage_path("app/tmp_videos/{$hash}");

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        Log::info("Processing chunk", [
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'filename' => $filename,
            'vlog_id' => $vlogId,
            'hash' => $hash,
            'tmp_dir' => $tmpDir,
        ]);

        // Save chunk with proper naming
        $chunkFile = "{$tmpDir}/chunk_{$chunkIndex}";
        $request->file('chunk')->move($tmpDir, "chunk_{$chunkIndex}");
        
        $chunkSize = filesize($chunkFile);
        Log::info("Chunk saved", [
            'path' => $chunkFile,
            'size' => $chunkSize,
            'exists' => file_exists($chunkFile),
        ]);

        // Save metadata on first chunk
        if ($chunkIndex === 0) {
            $metaData = [
                'title' => $request->title,
                'category' => $request->category,
                'date' => $request->date,
                'content' => $request->content,
                'description' => $request->description,
                'is_active' => $request->is_active ?? 1,
            ];

            // Poster handling
            if ($request->hasFile('poster')) {
                $posterFile = $request->file('poster');
                $posterName = time().'_'.$posterFile->getClientOriginalName();
                $posterPath = public_path('vlogs/posters');
                if (! file_exists($posterPath)) {
                    mkdir($posterPath, 0777, true);
                }
                $posterFile->move($posterPath, $posterName);
                $metaData['poster'] = '/vlogs/posters/'.$posterName;
            }

            // Save metadata to tmp
            file_put_contents("{$tmpDir}/meta.json", json_encode($metaData));
            Log::info("Metadata saved for upload session", ['hash' => $hash]);
        }

        // Not last chunk - return
        if ($chunkIndex + 1 < $totalChunks) {
            $currentChunk = $chunkIndex + 1;
            return response()->json([
                'success' => true, 
                'message' => "Chunk {$currentChunk}/{$totalChunks} uploaded successfully",
            ]);
        }

        // ============================================
        // LAST CHUNK - ASSEMBLE COMPLETE VIDEO
        // ============================================
        Log::info("Last chunk received, assembling video from {$totalChunks} chunks");

        // Verify all chunks exist
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = "{$tmpDir}/chunk_{$i}";
            if (!file_exists($chunkPath)) {
                Log::error("Missing chunk during assembly", ['chunk' => $i, 'path' => $chunkPath]);
                return response()->json([
                    'success' => false, 
                    'message' => "Missing chunk {$i}. Upload may have been corrupted."
                ], 500);
            }
        }

        // Create final video directory
        $finalDir = public_path('vlogs/videos');
        if (! is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $finalName = time().'_'.$filename;
        $finalPath = "{$finalDir}/{$finalName}";
        
        Log::info("Assembling video to: {$finalPath}");

        // Open output file for writing
        $out = fopen($finalPath, 'wb');
        if (!$out) {
            Log::error("Failed to create output file: {$finalPath}");
            return response()->json(['success' => false, 'message' => 'Failed to create video file'], 500);
        }

        $totalBytesWritten = 0;

        // Concatenate all chunks in correct order
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = "{$tmpDir}/chunk_{$i}";
            
            $in = fopen($chunkPath, 'rb');
            if (!$in) {
                fclose($out);
                Log::error("Failed to read chunk: {$chunkPath}");
                return response()->json(['success' => false, 'message' => "Failed to read chunk {$i}"], 500);
            }

            // Copy chunk to final file
            $bytesCopied = stream_copy_to_stream($in, $out);
            $totalBytesWritten += $bytesCopied;
            
            fclose($in);
            
            Log::info("Copied chunk {$i}", [
                'bytes' => $bytesCopied,
                'total_so_far' => $totalBytesWritten,
            ]);
            
            // Delete chunk after successful copy
            unlink($chunkPath);
        }

        fclose($out);
        
        $finalSize = filesize($finalPath);
        Log::info("Video assembly complete", [
            'path' => $finalPath,
            'size_bytes' => $finalSize,
            'size_mb' => round($finalSize / (1024 * 1024), 2),
            'total_written' => $totalBytesWritten,
        ]);

        // Verify final file size
        if ($finalSize === 0) {
            Log::error("Final video file is empty!");
            unlink($finalPath);
            return response()->json(['success' => false, 'message' => 'Video assembly failed - empty file'], 500);
        }

        // Load metadata
        $metaFile = "{$tmpDir}/meta.json";
        if (!file_exists($metaFile)) {
            Log::error("Missing metadata file: {$metaFile}");
            unlink($finalPath);
            return response()->json(['success' => false, 'message' => 'Missing metadata'], 500);
        }

        $meta = json_decode(file_get_contents($metaFile), true);
        unlink($metaFile);
        
        // Clean up temp directory
        @rmdir($tmpDir);

        // Create or update vlog record
        if ($vlogId) {
            $vlog = Vlog::findOrFail($vlogId);
            
            // Delete old video if exists
            if ($vlog->video && file_exists(public_path($vlog->video))) {
                unlink(public_path($vlog->video));
                Log::info("Deleted old video: {$vlog->video}");
            }
            
            $vlog->update(array_merge($meta, [
                'video' => "/vlogs/videos/{$finalName}",
            ]));
            
            Log::info("Vlog updated successfully", ['id' => $vlog->id]);
        } else {
            $vlog = Vlog::create(array_merge($meta, [
                'video' => "/vlogs/videos/{$finalName}",
            ]));
            
            Log::info("Vlog created successfully", ['id' => $vlog->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vlog saved successfully',
            'data' => $vlog,
        ]);
    }

    // Admin-only store (fallback - non-chunked)
    public function store(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can create vlogs.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'video' => 'nullable|string',
            'poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($request->hasFile('poster')) {
            $posterFile = $request->file('poster');
            $posterName = time().'_'.$posterFile->getClientOriginalName();
            $posterPath = public_path('vlogs/posters');
            if (! file_exists($posterPath)) {
                mkdir($posterPath, 0777, true);
            }
            $posterFile->move($posterPath, $posterName);
            $validated['poster'] = '/vlogs/posters/'.$posterName;
        }

        $vlog = Vlog::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vlog created successfully',
            'data' => $vlog,
        ], 201);
    }

    // Admin-only update (non-chunked)
    public function update(Request $request, $id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can update vlogs.'], 403);
        }

        $vlog = Vlog::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'category' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'is_active' => 'boolean',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:102400',
            'poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        // Handle poster upload
        if ($request->hasFile('poster')) {
            if ($vlog->poster && file_exists(public_path($vlog->poster))) {
                unlink(public_path($vlog->poster));
            }

            $posterFile = $request->file('poster');
            $posterName = time().'_'.$posterFile->getClientOriginalName();
            $posterPath = public_path('vlogs/posters');
            if (! file_exists($posterPath)) {
                mkdir($posterPath, 0777, true);
            }
            $posterFile->move($posterPath, $posterName);
            $validated['poster'] = '/vlogs/posters/'.$posterName;
        }

        // Handle video upload (non-chunked fallback)
        if ($request->hasFile('video')) {
            if ($vlog->video && file_exists(public_path($vlog->video))) {
                unlink(public_path($vlog->video));
            }

            $file = $request->file('video');
            $filename = time().'_'.$file->getClientOriginalName();
            $destination = public_path('vlogs/videos');
            if (! file_exists($destination)) {
                mkdir($destination, 0777, true);
            }

            $file->move($destination, $filename);
            $validated['video'] = '/vlogs/videos/'.$filename;
        }

        $vlog->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vlog updated successfully',
            'data' => $vlog->fresh(),
        ]);
    }

    // Admin-only delete
    public function destroy($id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can delete vlogs.'], 403);
        }

        $vlog = Vlog::findOrFail($id);
        
        // Delete video file
        if ($vlog->video && file_exists(public_path($vlog->video))) {
            unlink(public_path($vlog->video));
        }

        // Delete poster file
        if ($vlog->poster && file_exists(public_path($vlog->poster))) {
            unlink(public_path($vlog->poster));
        }

        $vlog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vlog deleted successfully',
        ]);
    }
}