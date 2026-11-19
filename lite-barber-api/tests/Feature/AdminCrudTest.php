<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_owner_can_update_full_shop_visual_configuration_and_logo(): void
    {
        Storage::fake('public');

        $shop = Shop::create([
            'name' => 'Barbearia Visual',
            'slug' => 'barbearia-visual',
            'opening_time' => '08:00:00',
            'closing_time' => '19:00:00',
            'slot_duration' => 30,
            'working_days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin Visual',
            'email' => 'admin-visual@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/shop', [
            'name' => 'Studio Visual Pro',
            'opening_time' => '09:00:00',
            'closing_time' => '20:00:00',
            'slot_duration' => 45,
            'lunch_start' => '12:00',
            'lunch_end' => '13:00',
            'working_days' => [2, 3, 4, 5, 6],
            'unavailable_dates' => ['2026-12-25', '2026-12-31'],
            'theme_mode' => 'light-wine',
            'theme_color' => '#800020',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Studio Visual Pro')
            ->assertJsonPath('slot_duration', 45)
            ->assertJsonPath('lunch_start', '12:00')
            ->assertJsonPath('lunch_end', '13:00')
            ->assertJsonPath('working_days', [2, 3, 4, 5, 6])
            ->assertJsonPath('unavailable_dates', ['2026-12-25', '2026-12-31'])
            ->assertJsonPath('theme_mode', 'light-wine')
            ->assertJsonPath('theme_color', '#800020');

        $this->putJson('/api/admin/shop', [
            'lunch_start' => '',
            'lunch_end' => '',
        ])
            ->assertOk()
            ->assertJsonPath('lunch_start', null)
            ->assertJsonPath('lunch_end', null);

        $this->postJson('/api/admin/shop/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->whereType('logo_url', 'string')
                ->etc()
            );
    }
}
