<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_services_and_professionals(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia CRUD',
            'slug' => 'barbearia-crud',
            'opening_time' => '08:00:00',
            'closing_time' => '19:00:00',
            'working_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Administrador',
            'email' => 'admin-crud@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $service = $this->postJson('/api/admin/services', [
            'name' => 'Corte Premium',
            'description' => 'Corte completo',
            'price' => 75,
            'duration_minutes' => 45,
            'display_order' => 2,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $serviceId = $service->json('service.id');

        $this->putJson('/api/admin/services/'.$serviceId, [
            'name' => 'Corte Especial',
            'price' => 80,
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Corte Especial');

        $professional = $this->postJson('/api/admin/professionals', [
            'name' => 'Carlos',
            'phone' => '11999999999',
            'specialties' => ['Corte', 'Barba'],
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $professionalId = $professional->json('professional.id');

        $this->putJson('/api/admin/professionals/'.$professionalId, [
            'phone' => '11888888888',
        ])
            ->assertOk()
            ->assertJsonPath('phone', '11888888888');

        $this->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Corte Especial']);

        $this->getJson('/api/admin/professionals')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Carlos']);

        $this->deleteJson('/api/admin/services/'.$serviceId)
            ->assertNoContent();

        $this->deleteJson('/api/admin/professionals/'.$professionalId)
            ->assertNoContent();
    }

    public function test_owner_cannot_configure_invalid_shop_hours(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Horários',
            'slug' => 'barbearia-horarios',
            'opening_time' => '08:00:00',
            'closing_time' => '18:00:00',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin Horários',
            'email' => 'admin-horarios@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/shop', [
            'opening_time' => '19:00',
            'closing_time' => '18:00',
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'O horário de fechamento deve ser posterior ao horário de abertura.',
            ]);
    }
}
