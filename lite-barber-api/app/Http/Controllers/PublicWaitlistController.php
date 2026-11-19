<?php

namespace App\Http\Controllers;

use App\Services\ShopService;
use App\Services\WaitlistService;
use Illuminate\Http\Request;

class PublicWaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
        private readonly ShopService $shopService
    ) {}

    public function store(Request $request, $slug)
    {
        $shop = $this->shopService->findActiveBySlug($slug);

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $result = $this->waitlistService->createPublicEntry($shop, $validated);

        return response()->json([
            'success' => true,
            'entry' => $result['entry'],
            'position' => $result['position'],
        ], 201);
    }

    public function status($slug)
    {
        $shop = $this->shopService->findActiveBySlug($slug);
        $status = $this->waitlistService->publicStatus($shop);

        return response()->json([
            'success' => true,
            ...$status,
        ]);
    }
}
