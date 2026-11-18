<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Waitlist;
use Illuminate\Http\Request;

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

    private function normalizeNumber(float $value): int|float
    {
        $rounded = round($value, 2);

        return fmod($rounded, 1.0) === 0.0 ? (int) $rounded : $rounded;
    }
}
