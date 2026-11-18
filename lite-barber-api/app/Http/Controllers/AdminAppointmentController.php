<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $shopId = $request->user()->shop_id;

        $query = Appointment::where('shop_id', $shopId)
            ->with(['professional', 'service'])
            ->when($request->query('date'), function ($q, $date) {
                return $q->whereDate('scheduled_at', $date);
            })
            ->when($request->query('start_date'), function ($q, $startDate) {
                return $q->whereDate('scheduled_at', '>=', $startDate);
            })
            ->when($request->query('end_date'), function ($q, $endDate) {
                return $q->whereDate('scheduled_at', '<=', $endDate);
            })
            ->when($request->query('from_date'), function ($q, $fromDate) {
                return $q->whereDate('scheduled_at', '>=', $fromDate);
            })
            ->when($request->query('professional_id'), function ($query, $professionalId) {
                return $query->where('professional_id', $professionalId);
            })
            ->when($request->query('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->query('week'), function ($query, $week) {
                $start = Carbon::parse($week)->startOfWeek();
                $end = $start->copy()->endOfWeek();

                return $query->whereBetween('scheduled_at', [$start, $end]);
            })
            ->orderBy('scheduled_at', 'asc');

        $perPage = (int) $request->query('per_page', 10);
        $appointments = $query->paginate($perPage);

        return response()->json($appointments);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:scheduled,confirmed,in_progress,completed,cancelled,no_show',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $appointment = Appointment::where('shop_id', $request->user()->shop_id)->findOrFail($id);

        $current = $appointment->status;
        $next = $request->status;

        $terminalStatuses = ['completed', 'cancelled', 'no_show'];
        if (in_array($current, $terminalStatuses, true) && $current !== $next) {
            return response()->json([
                'success' => false,
                'message' => 'Agendamentos finalizados não podem mudar de status.',
            ], 422);
        }

        $allowedTransitions = [
            'scheduled' => ['confirmed', 'in_progress', 'cancelled', 'no_show'],
            'confirmed' => ['in_progress', 'completed', 'cancelled', 'no_show'],
            'in_progress' => ['completed', 'cancelled', 'no_show'],
        ];

        if ($current !== $next && isset($allowedTransitions[$current]) && ! in_array($next, $allowedTransitions[$current], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Transição de status inválida.',
            ], 422);
        }

        $appointment->status = $next;

        if ($next === 'cancelled') {
            $appointment->cancelled_by = 'admin';
            $appointment->cancel_reason = $request->cancel_reason;
        }

        $appointment->save();

        // Se cancelado, verifica lista de espera para o mesmo horário
        $waitingClients = [];
        if ($request->status === 'cancelled') {
            $date = date('Y-m-d', strtotime($appointment->scheduled_at));
            $time = date('H:i', strtotime($appointment->scheduled_at));
            $waitingClients = AdminWaitlistController::checkForWaitingClients(
                $request->user()->shop_id,
                $date,
                $time,
                $appointment->professional_id
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Status atualizado',
            'appointment' => $appointment,
            'waiting_clients' => $waitingClients,
        ]);
    }
}
