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

class WaitlistFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_waitlist_calculates_position_and_public_status(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Fila',
            'slug' => 'barbearia-fila',
            'is_active' => true,
        ]);

        $first = $this->postJson('/api/shops/barbearia-fila/waitlist', [
            'customer_name' => 'Primeiro Cliente',
            'customer_phone' => '11999999999',
        ]);

        $first->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('position', 1);

        $second = $this->postJson('/api/shops/barbearia-fila/waitlist', [
            'customer_name' => 'Segundo Cliente',
            'customer_phone' => '11888888888',
        ]);

        $second->assertCreated()
            ->assertJsonPath('position', 2);

        $this->getJson('/api/shops/barbearia-fila/waitlist')
            ->assertOk()
            ->assertJsonPath('waiting_count', 2)
            ->assertJsonFragment(['name' => 'Primeiro Cliente'])
            ->assertJsonFragment(['name' => 'Segundo Cliente']);

        $this->assertDatabaseHas('waitlist', [
            'shop_id' => $shop->id,
            'client_name' => 'Primeiro Cliente',
            'status' => 'waiting',
        ]);
    }

    public function test_admin_can_notify_and_remove_waitlist_entry_of_own_tenant(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Fila Admin',
            'slug' => 'barbearia-fila-admin',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin',
            'email' => 'admin-fila@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $created = $this->postJson('/api/shops/barbearia-fila-admin/waitlist', [
            'customer_name' => 'Cliente da Fila',
            'customer_phone' => '11999999999',
        ])->assertCreated();

        $entryId = $created->json('entry.id');

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/waitlist/'.$entryId.'/notify')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('entry.status', 'notified');

        $this->patchJson('/api/admin/waitlist/'.$entryId.'/notify')
            ->assertStatus(422);

        $this->deleteJson('/api/admin/waitlist/'.$entryId)
            ->assertOk();

        $this->assertDatabaseMissing('waitlist', [
            'id' => $entryId,
            'status' => 'waiting',
        ]);
    }

    public function test_waitlist_reprocesses_positions_and_syncs_with_cancelled_appointment(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Fila Agenda',
            'slug' => 'barbearia-fila-agenda',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin Fila Agenda',
            'email' => 'admin-fila-agenda@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $professional = Professional::create([
            'shop_id' => $shop->id,
            'name' => 'Lucas',
            'is_active' => true,
        ]);

        $service = Service::create([
            'shop_id' => $shop->id,
            'name' => 'Corte + Barba',
            'price' => 70,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $scheduledAt = now()->copy()->addDays(2)->setTime(15, 0);
        $desiredDate = $scheduledAt->format('Y-m-d');

        $appointment = Appointment::create([
            'shop_id' => $shop->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'client_name' => 'Cliente Agendado',
            'client_phone' => '11999990000',
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(45),
            'price_snapshot' => 70,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($admin);

        $firstWait = $this->postJson('/api/admin/waitlist', [
            'client_name' => 'Espera 1',
            'client_phone' => '11999990001',
            'desired_date' => $desiredDate,
            'desired_time' => '15:00',
            'professional_id' => $professional->id,
            'service_id' => $service->id,
        ])->assertCreated();

        $secondWait = $this->postJson('/api/admin/waitlist', [
            'client_name' => 'Espera 2',
            'client_phone' => '11999990002',
            'desired_date' => $desiredDate,
            'desired_time' => '15:00',
            'professional_id' => $professional->id,
            'service_id' => $service->id,
        ])->assertCreated();

        $firstId = $firstWait->json('entry.id');
        $secondId = $secondWait->json('entry.id');

        $this->patchJson('/api/admin/appointments/'.$appointment->id.'/status', [
            'status' => 'cancelled',
        ])
            ->assertOk()
            ->assertJsonCount(2, 'waiting_clients')
            ->assertJsonPath('waiting_clients.0.id', $firstId)
            ->assertJsonPath('waiting_clients.0.position', 1)
            ->assertJsonPath('waiting_clients.1.id', $secondId)
            ->assertJsonPath('waiting_clients.1.position', 2);

        $this->patchJson('/api/admin/waitlist/'.$firstId.'/notify')
            ->assertOk();

        $this->getJson('/api/admin/waitlist')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $secondId,
                'client_name' => 'Espera 2',
                'position' => 1,
            ]);
    }
}
