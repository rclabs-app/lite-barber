<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Review;
use App\Services\ShopService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicReviewController extends Controller
{
    public function __construct(
        private readonly ShopService $shopService
    ) {}

    public function index($slug)
    {
        $shop = $this->shopService->findActiveBySlug($slug);

        $baseQuery = Review::withoutGlobalScopes()
            ->where('shop_id', $shop->id)
            ->where('is_approved', true);

        $totalReviews = (clone $baseQuery)->count();
        $averageRating = $totalReviews > 0 ? round((float) (clone $baseQuery)->avg('rating'), 1) : 5.0;

        $reviews = (clone $baseQuery)
            ->with([
                'professional' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'),
                'service' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'),
            ])
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => [
                'average_rating' => $averageRating,
                'total_reviews' => $totalReviews,
            ],
            'reviews' => $reviews,
        ]);
    }

    public function showByToken(string $token)
    {
        $appointment = Appointment::withoutGlobalScopes()
            ->with([
                'shop' => fn ($q) => $q->select('id', 'name', 'slug', 'theme_color', 'theme_mode', 'logo_url'),
                'professional' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'),
                'service' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'price'),
                'review' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->where('review_token', $token)
            ->firstOrFail();

        $alreadyReviewed = $appointment->review !== null;

        return response()->json([
            'success' => true,
            'appointment' => $appointment,
            'already_reviewed' => $alreadyReviewed,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $appointment = Appointment::withoutGlobalScopes()
            ->with(['shop', 'review'])
            ->where('review_token', $token)
            ->firstOrFail();

        if ($appointment->status !== 'completed') {
            throw new HttpException(422, 'Apenas atendimentos finalizados podem ser avaliados.');
        }

        if ($appointment->review !== null) {
            throw new HttpException(422, 'Este atendimento já foi avaliado anteriormente.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::create([
            'shop_id' => $appointment->shop_id,
            'appointment_id' => $appointment->id,
            'professional_id' => $appointment->professional_id,
            'service_id' => $appointment->service_id,
            'client_name' => $appointment->client_name,
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'is_approved' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avaliação enviada com sucesso! Muito obrigado pelo feedback.',
            'review' => $review,
        ], 201);
    }
}
