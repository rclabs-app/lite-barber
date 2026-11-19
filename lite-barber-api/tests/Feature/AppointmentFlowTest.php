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

class AppointmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_cancel_appointment_and_client_can_rebook_the_slot(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Fluxo',
            'slug' => 'barbearia-fluxo',
            'opening_time' => '09:00:00',
            'closing_time' => '18:00:00',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin',
            'email' => 'admin-fluxo@teste.com',
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

        $scheduledAt = now()->copy()->addDays(7)->setTime(14, 0);

        $appointment = Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente Original',
            'client_phone' => '11999999999',
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(30),
            'price_snapshot' => 50,
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/appointments/'.$appointment->id.'/status', [
            'status' => 'cancelled',
            'cancel_reason' => 'Reagendamento solicitado',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.status', 'cancelled');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
            'cancelled_by' => 'admin',
            'cancel_reason' => 'Reagendamento solicitado',
        ]);

        $this->patchJson('/api/admin/appointments/'.$appointment->id.'/status', [
            'status' => 'confirmed',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Agendamentos finalizados não podem mudar de status.']);

        $this->postJson('/api/shops/barbearia-fluxo/appointments', [
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'customer_name' => 'Novo Cliente',
            'customer_phone' => '11888888888',
        ])
            ->assertCreated();
    }

    public function test_admin_can_complete_and_mark_no_show_on_appointments(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Status',
            'slug' => 'barbearia-status',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin Status',
            'email' => 'admin-status@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $professional = Professional::create([
            'shop_id' => $shop->id,
            'name' => 'Rafael',
            'is_active' => true,
        ]);

        $service = Service::create([
            'shop_id' => $shop->id,
            'name' => 'Barba',
            'price' => 40,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $slotA = now()->copy()->setTime(11, 0);
        $appointmentA = Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente Direto',
            'client_phone' => '11999991111',
            'scheduled_at' => $slotA,
            'ends_at' => $slotA->copy()->addMinutes(30),
            'price_snapshot' => 40,
            'status' => 'scheduled',
        ]);

        $slotB = now()->copy()->setTime(15, 0);
        $appointmentB = Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente Falta',
            'client_phone' => '11999992222',
            'scheduled_at' => $slotB,
            'ends_at' => $slotB->copy()->addMinutes(30),
            'price_snapshot' => 40,
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/appointments/'.$appointmentA->id.'/status', [
            'status' => 'completed',
        ])
            ->assertOk()
            ->assertJsonPath('appointment.status', 'completed');

        $this->patchJson('/api/admin/appointments/'.$appointmentB->id.'/status', [
            'status' => 'no_show',
        ])
            ->assertOk()
            ->assertJsonPath('appointment.status', 'no_show');
    }

    public function test_client_can_view_and_cancel_appointment_via_public_token(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Token',
            'slug' => 'barbearia-token',
            'opening_time' => '09:00:00',
            'closing_time' => '18:00:00',
            'working_days' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ]);

        $professional = Professional::create([
            'shop_id' => $shop->id,
            'name' => 'Marcos',
            'is_active' => true,
        ]);

        $service = Service::create([
            'shop_id' => $shop->id,
            'name' => 'Corte',
            'price' => 60,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $scheduledAt = now()->copy()->addDays(3)->setTime(14, 0);

        $created = $this->postJson('/api/shops/barbearia-token/appointments', [
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'customer_name' => 'Cliente Token',
            'customer_phone' => '11999993333',
        ])->assertCreated();

        $token = $created->json('appointment.cancel_token');
        $this->assertNotEmpty($token);

        $this->getJson('/api/appointments/'.$token)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.client_name', 'Cliente Token')
            ->assertJsonPath('appointment.status', 'scheduled');

        $this->postJson('/api/appointments/'.$token.'/cancel', [
            'cancel_reason' => 'Imprevisto pessoal',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.status', 'cancelled')
            ->assertJsonPath('appointment.cancelled_by', 'client');

        $this->postJson('/api/appointments/'.$token.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este agendamento já foi finalizado ou cancelado.');
    }
}
