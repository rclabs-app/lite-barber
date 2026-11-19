<?php

namespace App\Http\Controllers;

use App\Services\AppointmentService;
use App\Services\ShopService;
use Illuminate\Http\Request;

class PublicAppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly ShopService $shopService
    ) {}

    public function store(Request $request, $slug)
    {
        $shop = $this->shopService->findActiveBySlug($slug);

        $validated = $request->validate([
            'professional_id' => 'required|integer',
            'service_id' => 'required|integer',
            'scheduled_at' => 'required|date',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $appointment = $this->appointmentService->createPublicAppointment($shop, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Agendamento efetuado com sucesso!',
            'appointment' => $appointment,
            'whatsapp_url' => $appointment->whatsapp_url ?? null,
        ], 201);
    }

    public function getBusySlots(Request $request, $slug)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'professional_id' => 'required|integer',
        ]);

        $shop = $this->shopService->findActiveBySlug($slug);

        $appointments = $this->appointmentService->getBusySlots(
            $shop,
            (int) $validated['professional_id'],
            $validated['date']
        );

        return response()->json($appointments);
    }

    public function showByToken(string $token)
    {
        $appointment = $this->appointmentService->findByToken($token);

        return response()->json([
            'success' => true,
            'appointment' => $appointment,
        ]);
    }

    public function cancelByToken(Request $request, string $token)
    {
        $validated = $request->validate([
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $result = $this->appointmentService->cancelByToken(
            $token,
            $validated['cancel_reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Agendamento cancelado com sucesso.',
            'appointment' => $result['appointment'],
            'waiting_clients' => $result['waiting_clients'],
        ]);
    }
}
