<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnershipInquiryRequest;
use App\Models\PartnershipInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PartnershipInquiryController extends Controller
{
    /**
     * POST /api/partnership-inquiries
     * Public endpoint — no auth required.
     */
    public function store(StorePartnershipInquiryRequest $request): JsonResponse
    {
        try {
            $inquiry = PartnershipInquiry::create($request->validated());

            // Optional: send notification email to the team
            // Mail::to(config('mail.partnership_notify'))->send(new PartnershipInquiryReceived($inquiry));

            return response()->json([
                'success' => true,
                'message' => 'Partnership inquiry submitted successfully. We will get back to you within 48 hours.',
                'data'    => [
                    'id'         => $inquiry->id,
                    'name'       => $inquiry->name,
                    'email'      => $inquiry->email,
                    'type'       => $inquiry->type,
                    'created_at' => $inquiry->created_at->toISOString(),
                ],
            ], 201);

        } catch (\Throwable $e) {
            Log::error('PartnershipInquiry store failed', [
                'error' => $e->getMessage(),
                'input' => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit inquiry. Please try again later.',
            ], 500);
        }
    }

    /**
     * GET /api/partnership-inquiries
     * Admin-only — protected by auth:sanctum middleware in routes.
     * Supports: ?type=retail|distributor|reseller|other  &status=new|contacted|converted|closed
     *           &search=term  &per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $query = PartnershipInquiry::query()->latest();

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $inquiries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $inquiries,
        ]);
    }

    /**
     * PATCH /api/partnership-inquiries/{id}/status
     * Admin-only.
     */
    public function updateStatus(Request $request, PartnershipInquiry $inquiry): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:new,contacted,converted,closed'],
        ]);

        $inquiry->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated.',
            'data'    => $inquiry->fresh(),
        ]);
    }
}