<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Store a newly created contact message.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20', 
            'message' => 'required|string|max:5000',
            'subject' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $contact = Contact::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone ?? null, 
                'message' => $request->message,
                'subject' => $request->subject,
                'status' => 'unread',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully! We will get back to you soon.',
                'data' => $contact,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of contact messages (for admin).
     */
    public function index(Request $request)
    {
        $query = Contact::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $contacts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $contacts,
        ]);
    }

    /**
     * Display the specified contact message.
     */
    public function show($id)
    {
        $contact = Contact::with(['replies.admin'])->find($id);

        if (! $contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found',
            ], 404);
        }

        // Mark as read if unread
        if ($contact->status === 'unread') {
            $contact->update(['status' => 'read']);
        }

        return response()->json([
            'success' => true,
            'data' => $contact,
        ]);
    }

    /**
     * Update the status of a contact message.
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:unread,read,replied',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contact = Contact::find($id);

        if (! $contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found',
            ], 404);
        }

        $contact->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $contact,
        ]);
    }

    /**
     * Reply to a contact message.
     */
    public function reply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contact = Contact::find($id);

        if (! $contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found',
            ], 404);
        }

        try {
            // Create reply record
            $reply = ContactReply::create([
                'contact_id' => $contact->id,
                'admin_id' => $request->user()->id,
                'message' => $request->message,
                'sent_at' => now(),
            ]);

            // Update contact status to replied
            $contact->update(['status' => 'replied']);

            // Return success (email sending will be handled by Next.js API)
            return response()->json([
                'success' => true,
                'message' => 'Reply saved successfully',
                'data' => $reply->load('admin'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
