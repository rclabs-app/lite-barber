<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AppointmentService
{
    public function __construct(
        private readonly WaitlistService $waitlistService
    ) {}

    public function listForAdmin(int $shopId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return Appointment::where('shop_id', $shopId)
            ->with(['professional', 'service'])
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('scheduled_at', $date))
            ->when($filters['start_date'] ?? null, fn ($q, $startDate) => $q->whereDate('scheduled_at', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn ($q, $endDate) => $q->whereDate('scheduled_at', '<=', $endDate))
            ->when($filters['from_date'] ?? null, fn ($q, $fromDate) => $q->whereDate('scheduled_at', '>=', $fromDate))
            ->when($filters['professional_id'] ?? null, fn ($q, $professionalId) => $q->where('professional_id', $professionalId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['week'] ?? null, function ($q, $week) {
                $start = Carbon::parse($week)->startOfWeek();
                $end = $start->copy()->endOfWeek();

                return $q->whereBetween('scheduled_at', [$start, $end]);
            })
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);
    }

    public function createPublicAppointment(Shop $shop, array $data): Appointment
    {
        $professional = Professional::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->findOrFail($data['professional_id']);

        $service = Service::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->findOrFail($data['service_id']);

        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $endsAt = $scheduledAt->copy()->addMinutes($service->duration_minutes);

        $validationError = $this->validateAvailabilityWindow($shop, $scheduledAt, $endsAt);
        if ($validationError) {
            throw new HttpException(422, $validationError);
        }

        try {
            return DB::transaction(function () use ($shop, $professional, $service, $data, $scheduledAt, $endsAt) {
                $conflict = Appointment::where('shop_id', $shop->id)
                    ->where('professional_id', $professional->id)
                    ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
                    ->where('scheduled_at', '<', $endsAt)
                    ->where('ends_at', '>', $scheduledAt)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw new HttpException(409, 'O horário solicitado já foi preenchido por outro cliente.');
                }

                return Appointment::create([
                    'shop_id' => $shop->id,
                    'professional_id' => $professional->id,
                    'service_id' => $service->id,
                    'client_name' => $data['customer_name'],
                    'client_phone' => $data['customer_phone'],
                    'notes' => $data['notes'] ?? null,
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => $endsAt,
                    'price_snapshot' => $service->price,
                    'status' => 'scheduled',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new HttpException(409, 'O horário solicitado já foi preenchido por outro cliente.');
        }
    }

    public function getBusySlots(Shop $shop, int $professionalId, string $date): Collection
    {
        $professional = Professional::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->findOrFail($professionalId);

        return Appointment::where('shop_id', $shop->id)
            ->where('professional_id', $professional->id)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress', 'completed'])
            ->get(['scheduled_at', 'ends_at'])
            ->map(fn ($appointment) => [
                'scheduled_at' => $appointment->scheduled_at->format('Y-m-d H:i:s'),
                'ends_at' => $appointment->ends_at->format('Y-m-d H:i:s'),
            ]);
    }

    public function updateStatus(int $shopId, int $appointmentId, string $nextStatus, ?string $cancelReason = null): array
    {
        $appointment = Appointment::where('shop_id', $shopId)->findOrFail($appointmentId);
        $current = $appointment->status;

        $terminalStatuses = ['completed', 'cancelled', 'no_show'];
        if (in_array($current, $terminalStatuses, true) && $current !== $nextStatus) {
            throw new HttpException(422, 'Agendamentos finalizados não podem mudar de status.');
        }

        $allowedTransitions = [
            'scheduled' => ['confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'],
            'confirmed' => ['in_progress', 'completed', 'cancelled', 'no_show'],
            'in_progress' => ['completed', 'cancelled', 'no_show'],
        ];

        if ($current !== $nextStatus && isset($allowedTransitions[$current]) && ! in_array($nextStatus, $allowedTransitions[$current], true)) {
            throw new HttpException(422, 'Transição de status inválida.');
        }

        $appointment->status = $nextStatus;

        if ($nextStatus === 'cancelled') {
            $appointment->cancelled_by = 'admin';
            $appointment->cancel_reason = $cancelReason;
        }

        $appointment->save();

        $waitingClients = [];
        if ($nextStatus === 'cancelled') {
            $date = date('Y-m-d', strtotime((string) $appointment->scheduled_at));
            $time = date('H:i', strtotime((string) $appointment->scheduled_at));
            $waitingClients = $this->waitlistService->findMatchingWaitingClients(
                $shopId,
                $date,
                $time,
                $appointment->professional_id
            );
        }

        return [
            'appointment' => $appointment,
            'waiting_clients' => $waitingClients,
        ];
    }

    public function findByToken(string $token): Appointment
    {
        return Appointment::withoutGlobalScopes()
            ->with(['shop', 'professional', 'service'])
            ->where('cancel_token', $token)
            ->firstOrFail();
    }

    public function cancelByToken(string $token, ?string $cancelReason = null): array
    {
        $appointment = $this->findByToken($token);

        if (in_array($appointment->status, ['completed', 'cancelled', 'no_show'], true)) {
            throw new HttpException(422, 'Este agendamento já foi finalizado ou cancelado.');
        }

        $appointment->status = 'cancelled';
        $appointment->cancelled_by = 'client';
        $appointment->cancel_reason = $cancelReason;
        $appointment->save();

        $date = date('Y-m-d', strtotime((string) $appointment->scheduled_at));
        $time = date('H:i', strtotime((string) $appointment->scheduled_at));
        $waitingClients = $this->waitlistService->findMatchingWaitingClients(
            (int) $appointment->shop_id,
            $date,
            $time,
            $appointment->professional_id
        );

        return [
            'appointment' => $appointment,
            'waiting_clients' => $waitingClients,
        ];
    }

    public function validateAvailabilityWindow(Shop $shop, Carbon $scheduledAt, Carbon $endsAt): ?string
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

        $opening = $this->parseShopTime($date, (string) $shop->opening_time);
        $closing = $this->parseShopTime($date, (string) $shop->closing_time);

        if ($scheduledAt->lt($opening) || $endsAt->gt($closing)) {
            return 'O horário solicitado está fora do horário de funcionamento.';
        }

        if ($shop->lunch_start && $shop->lunch_end) {
            $lunchStart = $this->parseShopTime($date, (string) $shop->lunch_start);
            $lunchEnd = $this->parseShopTime($date, (string) $shop->lunch_end);

            if ($scheduledAt->lt($lunchEnd) && $endsAt->gt($lunchStart)) {
                return 'O horário solicitado coincide com o intervalo de almoço.';
            }
        }

        return null;
    }

    private function parseShopTime(string $date, string $time): Carbon
    {
        $trimmed = trim($time);
        $normalized = strlen($trimmed) === 5 ? $trimmed.':00' : substr($trimmed, 0, 8);

        return Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$normalized);
    }
}
