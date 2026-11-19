<?php

namespace App\Http\Controllers;

use App\Services\WaitlistService;
use Illuminate\Http\Request;

class AdminWaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistService $waitlistService
    ) {}

    public function index(Request $request)
    {
        $list = $this->waitlistService->listForShop(
            (int) $request->user()->shop_id,
            (int) $request->query('per_page', 20)
        );

        return response()->json($list);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:150',
            'client_phone' => 'required|string|max:20',
            'desired_date' => 'required|date|after_or_equal:today',
            'desired_time' => 'required|date_format:H:i',
            'professional_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        $entry = $this->waitlistService->createAdminEntry(
            (int) $request->user()->shop_id,
            $validated
        );

        return response()->json([
            'success' => true,
            'entry' => $entry,
        ], 201);
    }

    public function markNotified(Request $request, $id)
    {
        $entry = $this->waitlistService->markNotified(
            (int) $request->user()->shop_id,
            (int) $id
        );

        return response()->json([
            'success' => true,
            'entry' => $entry,
            'whatsapp_url' => $entry->whatsapp_url ?? null,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->waitlistService->cancelEntry(
            (int) $request->user()->shop_id,
            (int) $id
        );

        return response()->json([
            'success' => true,
            'message' => 'Removido da lista de espera.',
        ]);
    }

    public static function checkForWaitingClients(int $shopId, string $date, string $time, ?int $professionalId = null): array
    {
        return app(WaitlistService::class)->findMatchingWaitingClients($shopId, $date, $time, $professionalId);
    }
}
