<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;

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
            ->orderBy('scheduled_at', 'asc');

        $perPage      = (int) $request->query('per_page', 10);
        $appointments = $query->paginate($perPage);

        return response()->json($appointments);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:scheduled,confirmed,in_progress,completed,cancelled,no_show'
        ]);

        $appointment = Appointment::where('shop_id', $request->user()->shop_id)->findOrFail($id);

        $appointment->status = $request->status;
        if ($request->status === 'cancelled') {
            $appointment->cancelled_by = 'admin';
        }

        $appointment->save();

        // Se cancelado, verifica lista de espera para o mesmo horário
        $waitingClients = [];
        if ($request->status === 'cancelled') {
            $date           = date('Y-m-d', strtotime($appointment->scheduled_at));
            $time           = date('H:i', strtotime($appointment->scheduled_at));
            $waitingClients = AdminWaitlistController::checkForWaitingClients(
                $request->user()->shop_id,
                $date,
                $time,
                $appointment->professional_id
            );
        }

        return response()->json([
            'message'         => 'Status atualizado',
            'appointment'     => $appointment,
            'waiting_clients' => $waitingClients,
        ]);
    }
}
