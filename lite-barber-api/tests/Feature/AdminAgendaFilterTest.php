<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAgendaFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_agenda_by_professional_status_and_week(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Agenda',
            'slug' => 'barbearia-agenda',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin',
            'email' => 'admin-agenda@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $professionalA = Professional::create([
            'shop_id' => $shop->id,
            'name' => 'João',
            'is_active' => true,
        ]);

        $professionalB = Professional::create([
            'shop_id' => $shop->id,
            'name' => 'Maria',
            'is_active' => true,
        ]);

        $service = Service::create([
            'shop_id' => $shop->id,
            'name' => 'Corte',
            'price' => 50,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $weekDate = now()->startOfWeek()->addDays(2)->setTime(10, 0);

        Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professionalA->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente A',
            'client_phone' => '11999999999',
            'scheduled_at' => $weekDate,
            'ends_at' => $weekDate->copy()->addMinutes(30),
            'price_snapshot' => 50,
            'status' => 'confirmed',
        ]);

        Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professionalB->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente B',
            'client_phone' => '11888888888',
            'scheduled_at' => $weekDate->copy()->addDay(),
            'ends_at' => $weekDate->copy()->addDay()->addMinutes(30),
            'price_snapshot' => 50,
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/appointments?professional_id='.$professionalA->id.'&status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['client_name' => 'Cliente A'])
            ->assertJsonMissing(['client_name' => 'Cliente B']);

        $this->getJson('/api/admin/appointments?week='.$weekDate->format('Y-m-d').'&per_page=100')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
