<?php

namespace App\Http\Controllers;

use App\Services\AppointmentService;
use Illuminate\Http\Request;

class AdminAppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {}

    public function index(Request $request)
    {
        $appointments = $this->appointmentService->listForAdmin(
            (int) $request->user()->shop_id,
            $request->only(['date', 'start_date', 'end_date', 'from_date', 'professional_id', 'status', 'week']),
            (int) $request->query('per_page', 10)
        );

        return response()->json($appointments);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,confirmed,in_progress,completed,cancelled,no_show',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $result = $this->appointmentService->updateStatus(
            (int) $request->user()->shop_id,
            (int) $id,
            $validated['status'],
            $validated['cancel_reason'] ?? null,
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Status atualizado',
            'appointment' => $result['appointment'],
            'waiting_clients' => $result['waiting_clients'],
        ]);
    }
}
