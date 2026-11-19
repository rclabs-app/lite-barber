<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Models\Waitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_real_shop_metrics(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Métricas',
            'slug' => 'barbearia-metricas',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin',
            'email' => 'admin-metricas@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $professional = Professional::create([
            'shop_id' => $shop->id,
            'name' => 'João',
            'is_active' => true,
        ]);

        $service = Service::create([
            'shop_id' => $shop->id,
            'name' => 'Corte',
            'price' => 50,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $today = now()->setTime(10, 0);

        Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente Concluído',
            'client_phone' => '11999999999',
            'scheduled_at' => $today,
            'ends_at' => $today->copy()->addMinutes(30),
            'price_snapshot' => 50,
            'status' => 'completed',
        ]);

        Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente No-show',
            'client_phone' => '11888888888',
            'scheduled_at' => $today->copy()->addHour(),
            'ends_at' => $today->copy()->addHour()->addMinutes(30),
            'price_snapshot' => 50,
            'status' => 'no_show',
        ]);

        Waitlist::create([
            'shop_id' => $shop->id,
            'client_name' => 'Cliente Fila',
            'client_phone' => '11777777777',
            'status' => 'waiting',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointments_today', 2)
            ->assertJsonPath('completed_today', 1)
            ->assertJsonPath('no_show_today', 1)
            ->assertJsonPath('no_show_rate', 50)
            ->assertJsonPath('revenue_today', 50)
            ->assertJsonPath('ticket_average', 50)
            ->assertJsonPath('appointments_month', 2)
            ->assertJsonPath('waitlist_count', 1)
            ->assertJsonFragment(['name' => 'João']);

        $this->getJson('/api/admin/reports?start_date='.$today->toDateString().'&end_date='.$today->toDateString())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total_appointments', 2)
            ->assertJsonPath('summary.completed_count', 1)
            ->assertJsonPath('summary.no_show_count', 1)
            ->assertJsonPath('summary.total_revenue', 50)
            ->assertJsonPath('summary.ticket_average', 50)
            ->assertJsonPath('by_professional.0.name', 'João')
            ->assertJsonPath('by_service.0.name', 'Corte');
    }
}
