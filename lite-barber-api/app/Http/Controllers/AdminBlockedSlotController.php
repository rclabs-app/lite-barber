<?php

namespace App\Http\Controllers;

use App\Models\BlockedSlot;
use App\Models\Professional;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminBlockedSlotController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $shopId = (int) $request->user()->shop_id;

        return BlockedSlot::where('shop_id', $shopId)
            ->with(['professional', 'creator'])
            ->when($request->query('professional_id'), fn ($q, $profId) => $q->where('professional_id', $profId))
            ->when($request->query('date'), fn ($q, $date) => $q->whereDate('starts_at', $date))
            ->orderBy('starts_at', 'asc')
            ->get();
    }

    public function store(Request $request)
    {
        $shopId = (int) $request->user()->shop_id;

        $validated = $request->validate([
            'professional_id' => 'required|integer',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        if (! Professional::where('shop_id', $shopId)->whereKey($validated['professional_id'])->exists()) {
            throw new HttpException(422, 'Profissional não pertence a esta barbearia.');
        }

        $blockedSlot = BlockedSlot::create([
            'shop_id' => $shopId,
            'professional_id' => $validated['professional_id'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'reason' => $validated['reason'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $this->auditLogService->record(
            $shopId,
            $request->user()?->id,
            'blocked_slot.created',
            'blocked_slot',
            (int) $blockedSlot->id,
            [
                'professional_id' => $blockedSlot->professional_id,
                'starts_at' => (string) $blockedSlot->starts_at,
                'ends_at' => (string) $blockedSlot->ends_at,
                'reason' => $blockedSlot->reason,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Bloqueio de horário registrado.',
            'blocked_slot' => $blockedSlot->load(['professional', 'creator']),
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $shopId = (int) $request->user()->shop_id;

        $blockedSlot = BlockedSlot::where('shop_id', $shopId)->findOrFail($id);
        $blockedSlot->delete();

        $this->auditLogService->record(
            $shopId,
            $request->user()?->id,
            'blocked_slot.deleted',
            'blocked_slot',
            (int) $id,
            ['reason' => $blockedSlot->reason]
        );

        return response()->json([
            'success' => true,
            'message' => 'Bloqueio de horário removido.',
        ]);
    }
}
