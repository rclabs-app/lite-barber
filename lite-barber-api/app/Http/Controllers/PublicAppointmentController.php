<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Shop;
use App\Models\Service;
use App\Models\Professional;
use App\Models\Appointment;
use Illuminate\Support\Carbon;

class PublicAppointmentController extends Controller
{
    public function store(Request $request, $slug)
    {
        $shop = Shop::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $request->validate([
            'professional_id' => 'required|integer',
            'service_id' => 'required|integer',
            'scheduled_at' => 'required|date',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
        ]);

        $professional = Professional::where('shop_id', $shop->id)->findOrFail($request->professional_id);
        $service = Service::where('shop_id', $shop->id)->findOrFail($request->service_id);

        $scheduledAt = Carbon::parse($request->scheduled_at);
        $endsAt = $scheduledAt->copy()->addMinutes($service->duration_minutes);

        $conflict = Appointment::where('professional_id', $professional->id)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->where(function($query) use ($scheduledAt, $endsAt) {
                $query->whereBetween('scheduled_at', [$scheduledAt, $endsAt->copy()->subMinute()])
                      ->orWhereBetween('ends_at', [$scheduledAt->copy()->addMinute(), $endsAt])
                      ->orWhere(function($q) use ($scheduledAt, $endsAt) {
                          $q->where('scheduled_at', '<=', $scheduledAt)
                            ->where('ends_at', '>=', $endsAt);
                      });
            })
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'O horário solicitado já foi preenchido por outro cliente.'], 409);
        }

        $appointment = Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'client_name' => $request->customer_name,
            'client_phone' => $request->customer_phone,
            'notes' => $request->notes ?? null,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $endsAt,
            'price_snapshot' => $service->price,
            'status' => 'scheduled'
        ]);

        return response()->json(['message' => 'Agendamento efetuado com sucesso!', 'appointment' => $appointment], 201);
    }

    public function getBusySlots(Request $request, $slug)
    {
        $shop = Shop::where('slug', $slug)->firstOrFail();
        $date = $request->query('date');
        $professionalId = $request->query('professional_id');

        if (!$date || !$professionalId) {
            return response()->json([]);
        }

        $appointments = Appointment::where('shop_id', $shop->id)
            ->where('professional_id', $professionalId)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress', 'completed'])
            ->get(['scheduled_at', 'ends_at'])
            ->map(function($app) {
                return [
                    'scheduled_at' => $app->scheduled_at->format('Y-m-d H:i:s'),
                    'ends_at' => $app->ends_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($appointments);
    }
}
