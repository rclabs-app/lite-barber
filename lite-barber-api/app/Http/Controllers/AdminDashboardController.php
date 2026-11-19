<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Waitlist;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function metrics(Request $request)
    {
        $shopId = $request->user()->shop_id;
        $now = now();
        $today = $now->toDateString();

        $todayAppointments = Appointment::where('shop_id', $shopId)
            ->with(['professional', 'service'])
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $appointmentsToday = $todayAppointments->count();
        $completedTodayCollection = $todayAppointments->where('status', 'completed');
        $completedToday = $completedTodayCollection->count();
        $noShowToday = $todayAppointments->where('status', 'no_show')->count();

        $noShowRate = $appointmentsToday > 0
            ? (int) round(($noShowToday / $appointmentsToday) * 100)
            : 0;

        $revenueTodayRaw = (float) $completedTodayCollection->sum(
            fn (Appointment $appointment) => (float) ($appointment->price_snapshot ?? 0)
        );
        $ticketAverageRaw = $completedToday > 0
            ? $revenueTodayRaw / $completedToday
            : 0.0;

        $appointmentsMonth = Appointment::where('shop_id', $shopId)
            ->whereBetween('scheduled_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ])
            ->count();

        $waitlistCount = Waitlist::where('shop_id', $shopId)
            ->where('status', 'waiting')
            ->count();

        $professionalRanking = Appointment::where('shop_id', $shopId)
            ->whereNotNull('professional_id')
            ->with('professional')
            ->get()
            ->groupBy('professional_id')
            ->map(function ($appointments) {
                $professional = $appointments->first()?->professional;
                if (! $professional) {
                    return null;
                }

                $completed = $appointments->where('status', 'completed');
                $completedCount = $completed->count();
                $revenueRaw = (float) $completed->sum(
                    fn (Appointment $appointment) => (float) ($appointment->price_snapshot ?? 0)
                );
                $revenue = $this->normalizeNumber($revenueRaw);

                return [
                    'professional_id' => $professional->id,
                    'name' => $professional->name,
                    'completed_count' => $completedCount,
                    'atendimentos' => $completedCount,
                    'revenue' => $revenue,
                    'faturamento' => $revenue,
                ];
            })
            ->filter()
            ->sortByDesc('completed_count')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'appointments_today' => $appointmentsToday,
            'completed_today' => $completedToday,
            'no_show_today' => $noShowToday,
            'no_show_rate' => $noShowRate,
            'revenue_today' => $this->normalizeNumber($revenueTodayRaw),
            'ticket_average' => $this->normalizeNumber($ticketAverageRaw),
            'appointments_month' => $appointmentsMonth,
            'waitlist_count' => $waitlistCount,
            'professional_ranking' => $professionalRanking,
            'today_appointments' => $todayAppointments,
        ]);
    }

    public function reports(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        $shopId = $request->user()->shop_id;
        $start = $request->filled('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->startOfMonth();
        $end = $request->filled('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfMonth();

        $appointments = Appointment::where('shop_id', $shopId)
            ->with(['professional', 'service'])
            ->whereBetween('scheduled_at', [$start, $end])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        $totalAppointments = $appointments->count();
        $completed = $appointments->where('status', 'completed');
        $completedCount = $completed->count();
        $cancelledCount = $appointments->where('status', 'cancelled')->count();
        $noShowCount = $appointments->where('status', 'no_show')->count();

        $totalRevenueRaw = (float) $completed->sum(
            fn (Appointment $appointment) => (float) ($appointment->price_snapshot ?? 0)
        );
        $ticketAverageRaw = $completedCount > 0 ? $totalRevenueRaw / $completedCount : 0.0;

        $byProfessional = $appointments
            ->whereNotNull('professional_id')
            ->groupBy('professional_id')
            ->map(function ($group) {
                $professional = $group->first()?->professional;
                $completedItems = $group->where('status', 'completed');
                $revenueRaw = (float) $completedItems->sum(fn ($a) => (float) ($a->price_snapshot ?? 0));

                return [
                    'professional_id' => $professional?->id,
                    'name' => $professional?->name ?? 'Sem profissional',
                    'total_appointments' => $group->count(),
                    'completed_count' => $completedItems->count(),
                    'revenue' => $this->normalizeNumber($revenueRaw),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        $byService = $appointments
            ->whereNotNull('service_id')
            ->groupBy('service_id')
            ->map(function ($group) {
                $service = $group->first()?->service;
                $completedItems = $group->where('status', 'completed');
                $revenueRaw = (float) $completedItems->sum(fn ($a) => (float) ($a->price_snapshot ?? 0));

                return [
                    'service_id' => $service?->id,
                    'name' => $service?->name ?? 'Sem serviço',
                    'total_appointments' => $group->count(),
                    'completed_count' => $completedItems->count(),
                    'revenue' => $this->normalizeNumber($revenueRaw),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'total_appointments' => $totalAppointments,
                'completed_count' => $completedCount,
                'cancelled_count' => $cancelledCount,
                'no_show_count' => $noShowCount,
                'total_revenue' => $this->normalizeNumber($totalRevenueRaw),
                'ticket_average' => $this->normalizeNumber($ticketAverageRaw),
            ],
            'by_professional' => $byProfessional,
            'by_service' => $byService,
            'appointments' => $appointments,
        ]);
    }

    private function normalizeNumber(float $value): int|float
    {
        $rounded = round($value, 2);

        return fmod($rounded, 1.0) === 0.0 ? (int) $rounded : $rounded;
    }
}
