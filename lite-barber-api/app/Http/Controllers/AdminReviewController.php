<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $shopId = (int) $request->user()->shop_id;

        $reviews = Review::where('shop_id', $shopId)
            ->with(['professional', 'service', 'appointment'])
            ->when($request->query('professional_id'), fn ($q, $profId) => $q->where('professional_id', $profId))
            ->when($request->query('rating'), fn ($q, $rating) => $q->where('rating', $rating))
            ->when($request->has('is_approved'), fn ($q) => $q->where('is_approved', filter_var($request->query('is_approved'), FILTER_VALIDATE_BOOLEAN)))
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        $stats = [
            'total' => Review::where('shop_id', $shopId)->count(),
            'average' => round((float) (Review::where('shop_id', $shopId)->avg('rating') ?: 0), 1),
            'approved' => Review::where('shop_id', $shopId)->where('is_approved', true)->count(),
        ];

        return response()->json([
            'reviews' => $reviews,
            'stats' => $stats,
        ]);
    }

    public function toggleApproval(Request $request, $id)
    {
        $shopId = (int) $request->user()->shop_id;

        $review = Review::where('shop_id', $shopId)->findOrFail($id);
        $review->is_approved = ! $review->is_approved;
        $review->save();

        $this->auditLogService->record(
            $shopId,
            $request->user()?->id,
            'review.approval_toggled',
            'review',
            (int) $review->id,
            ['is_approved' => $review->is_approved]
        );

        return response()->json([
            'success' => true,
            'message' => $review->is_approved ? 'Avaliação aprovada.' : 'Avaliação ocultada.',
            'review' => $review,
        ]);
    }
}
