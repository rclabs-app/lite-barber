<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            'notes' => 'nullable|string|max:1000',
        ]);

        $professional = Professional::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->findOrFail($request->professional_id);

        $service = Service::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->findOrFail($request->service_id);

        $scheduledAt = Carbon::parse($request->scheduled_at);
        $endsAt = $scheduledAt->copy()->addMinutes($service->duration_minutes);

        $validationError = $this->validateAvailabilityWindow($shop, $scheduledAt, $endsAt);
        if ($validationError) {
            return response()->json(['success' => false, 'message' => $validationError], 422);
        }

        try {
            $appointment = DB::transaction(function () use ($shop, $professional, $service, $request, $scheduledAt, $endsAt) {
                $conflict = Appointment::where('shop_id', $shop->id)
                    ->where('professional_id', $professional->id)
                    ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
                    ->where('scheduled_at', '<', $endsAt)
                    ->where('ends_at', '>', $scheduledAt)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    abort(409, 'O horário solicitado já foi preenchido por outro cliente.');
                }

                return Appointment::create([
                    'shop_id' => $shop->id,
                    'professional_id' => $professional->id,
                    'service_id' => $service->id,
                    'client_name' => $request->customer_name,
                    'client_phone' => $request->customer_phone,
                    'notes' => $request->notes,
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => $endsAt,
                    'price_snapshot' => $service->price,
                    'status' => 'scheduled',
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'success' => false,
                'message' => 'O horário solicitado já foi preenchido por outro cliente.',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Agendamento efetuado com sucesso!',
            'appointment' => $appointment,
        ], 201);
    }

    public function getBusySlots(Request $request, $slug)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'professional_id' => 'required|integer',
        ]);

        $shop = Shop::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $professional = Professional::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->findOrFail($request->professional_id);

        $appointments = Appointment::where('shop_id', $shop->id)
            ->where('professional_id', $professional->id)
            ->whereDate('scheduled_at', $request->date)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress', 'completed'])
            ->get(['scheduled_at', 'ends_at'])
            ->map(fn ($appointment) => [
                'scheduled_at' => $appointment->scheduled_at->format('Y-m-d H:i:s'),
                'ends_at' => $appointment->ends_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json($appointments);
    }

    private function validateAvailabilityWindow(Shop $shop, Carbon $scheduledAt, Carbon $endsAt): ?string
    {
        if ($scheduledAt->lt(now())) {
            return 'Não é possível agendar em um horário passado.';
        }

        $workingDays = array_map('intval', $shop->working_days ?: []);
        if (! in_array((int) $scheduledAt->dayOfWeek, $workingDays, true)) {
            return 'A barbearia não atende neste dia da semana.';
        }

        $date = $scheduledAt->format('Y-m-d');
        $unavailableDates = $shop->unavailable_dates ?: [];
        if (in_array($date, $unavailableDates, true)) {
            return 'A barbearia não atende nesta data.';
        }

        $opening = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.substr((string) $shop->opening_time, 0, 8));
        $closing = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.substr((string) $shop->closing_time, 0, 8));

        if ($scheduledAt->lt($opening) || $endsAt->gt($closing)) {
            return 'O horário solicitado está fora do horário de funcionamento.';
        }

        if ($shop->lunch_start && $shop->lunch_end) {
            $lunchStart = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.substr((string) $shop->lunch_start, 0, 8));
            $lunchEnd = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.substr((string) $shop->lunch_end, 0, 8));

            if ($scheduledAt->lt($lunchEnd) && $endsAt->gt($lunchStart)) {
                return 'O horário solicitado coincide com o intervalo de almoço.';
            }
        }

        return null;
    }
}
