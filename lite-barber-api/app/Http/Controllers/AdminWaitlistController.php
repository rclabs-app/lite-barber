<?php

namespace App\Http\Controllers;

use App\Models\Waitlist;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AdminWaitlistController extends Controller
{
    /**
     * Lista as entradas de espera da barbearia, mais recentes primeiro.
     */
    public function index(Request $request)
    {
        $shopId = $request->user()->shop_id;

        $list = Waitlist::where('shop_id', $shopId)
            ->with(['professional', 'service'])
            ->whereIn('status', ['waiting', 'notified'])
            ->orderBy('desired_date', 'asc')
            ->orderBy('desired_time', 'asc')
            ->orderBy('created_at', 'asc')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($list);
    }

    /**
     * Adiciona um cliente à lista de espera para um horário específico.
     */
    public function store(Request $request)
    {
        $shopId = $request->user()->shop_id;

        $request->validate([
            'client_name' => 'required|string|max:150',
            'client_phone' => 'required|string|max:20',
            'desired_date' => 'required|date|after_or_equal:today',
            'desired_time' => 'required|date_format:H:i',
            'professional_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($request->professional_id && ! \App\Models\Professional::where('shop_id', $shopId)->whereKey($request->professional_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Profissional não pertence a esta barbearia.'], 422);
        }

        if ($request->service_id && ! \App\Models\Service::where('shop_id', $shopId)->whereKey($request->service_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Serviço não pertence a esta barbearia.'], 422);
        }

        $entry = Waitlist::create([
            'shop_id'      => $shopId,
            'client_name'  => $request->client_name,
            'client_phone' => $request->client_phone,
            'desired_date' => $request->desired_date,
            'desired_time' => $request->desired_time,
            'professional_id'    => $request->professional_id,
            'service_id'   => $request->service_id,
            'notes'        => $request->notes,
            'status'       => 'waiting',
        ]);

        return response()->json([
            'success' => true,
            'entry' => $entry->load(['professional', 'service']),
        ], 201);
    }

    /**
     * Marca como notificado (admin avisou o cliente manualmente via WhatsApp).
     */
    public function markNotified(Request $request, $id)
    {
        $shopId = $request->user()->shop_id;
        $entry  = Waitlist::where('shop_id', $shopId)->findOrFail($id);

        if ($entry->status !== 'waiting') {
            return response()->json([
                'success' => false,
                'message' => 'Somente entradas aguardando podem ser notificadas.',
            ], 422);
        }

        $entry->status = 'notified';
        $entry->notified_at = now();
        $entry->save();

        return response()->json([
            'success' => true,
            'entry' => $entry,
        ]);
    }

    /**
     * Remove da lista de espera (cliente desistiu ou horário foi preenchido).
     */
    public function destroy(Request $request, $id)
    {
        $shopId = $request->user()->shop_id;
        $entry  = Waitlist::where('shop_id', $shopId)->findOrFail($id);

        if ($entry->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Esta entrada já foi removida.',
            ], 422);
        }

        $entry->status = 'cancelled';
        $entry->save();

        return response()->json([
            'success' => true,
            'message' => 'Removido da lista de espera.',
        ]);
    }

    /**
     * Verifica se há alguém na lista de espera para um horário liberado.
     * Chamado internamente quando um agendamento é cancelado.
     */
    public static function checkForWaitingClients(int $shopId, string $date, string $time, ?int $professionalId = null): array
    {
        $query = Waitlist::where('shop_id', $shopId)
            ->where('status', 'waiting')
            ->whereDate('desired_date', $date)
            ->where('desired_time', $time);

        if ($professionalId) {
            $query->where(function ($q) use ($professionalId) {
                $q->where('professional_id', $professionalId)->orWhereNull('professional_id');
            });
        }

        return $query->with(['professional', 'service'])->orderBy('created_at', 'asc')->get()->toArray();
    }
}
