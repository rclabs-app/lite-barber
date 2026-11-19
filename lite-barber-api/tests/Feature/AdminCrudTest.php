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

    public function test_plan_limits_and_social_maps_fields_are_enforced(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Lite',
            'slug' => 'barbearia-lite',
            'plan' => 'free',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin Lite',
            'email' => 'admin-lite@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        for ($i = 1; $i <= 2; $i++) {
            $this->postJson('/api/admin/professionals', [
                'name' => "Profissional {$i}",
            ])->assertCreated();
        }

        $this->postJson('/api/admin/professionals', [
            'name' => 'Profissional Excedente',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Limite do plano atingido: seu plano permite até 2 profissionais.');

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/admin/services', [
                'name' => "Serviço {$i}",
                'price' => 40,
                'duration_minutes' => 30,
            ])->assertCreated();
        }

        $this->postJson('/api/admin/services', [
            'name' => 'Serviço Excedente',
            'price' => 40,
            'duration_minutes' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Limite do plano atingido: seu plano permite até 5 serviços.');

        $shop->update(['plan' => 'pro']);

        $this->postJson('/api/admin/professionals', [
            'name' => 'Profissional Pro 3',
        ])->assertCreated();

        $this->postJson('/api/admin/services', [
            'name' => 'Serviço Pro 6',
            'price' => 55,
            'duration_minutes' => 30,
        ])->assertCreated();

        $this->putJson('/api/admin/shop', [
            'whatsapp_phone' => '11999998888',
            'instagram_url' => '@barbearialite',
            'address' => 'Av. Paulista, 1000',
            'maps_url' => 'https://maps.google.com/?q=Av+Paulista+1000',
        ])
            ->assertOk()
            ->assertJsonPath('whatsapp_phone', '11999998888')
            ->assertJsonPath('instagram_url', '@barbearialite')
            ->assertJsonPath('address', 'Av. Paulista, 1000')
            ->assertJsonPath('maps_url', 'https://maps.google.com/?q=Av+Paulista+1000');

        $this->getJson('/api/shops/barbearia-lite')
            ->assertOk()
            ->assertJsonPath('whatsapp_phone', '11999998888')
            ->assertJsonPath('instagram_url', '@barbearialite');
    }

    public function test_owner_can_upgrade_plan_configure_i18n_and_view_audit_logs(): void
    {
        $shop = Shop::create([
            'name' => 'Barbearia Global',
            'slug' => 'barbearia-global',
            'plan' => 'free',
            'is_active' => true,
        ]);

        $admin = User::create([
            'shop_id' => $shop->id,
            'name' => 'Admin Global',
            'email' => 'admin-global@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/shop/plan', [
            'plan' => 'pro',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shop.plan', 'pro');

        $this->putJson('/api/admin/shop', [
            'currency' => 'EUR',
            'locale' => 'pt-PT',
            'timezone' => 'Europe/Lisbon',
        ])
            ->assertOk()
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('locale', 'pt-PT')
            ->assertJsonPath('timezone', 'Europe/Lisbon');

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/admin/professionals', [
                'name' => "Profissional {$i}",
            ])->assertCreated();
        }

        $this->patchJson('/api/admin/shop/plan', [
            'plan' => 'free',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Reduza a quantidade de profissionais (máx. 2) e serviços (máx. 5) antes de mudar para o plano Lite.'
            );

        $this->getJson('/api/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['action' => 'shop.plan_updated'])
            ->assertJsonFragment(['action' => 'shop.config_updated'])
            ->assertJsonFragment(['action' => 'professional.created']);
    }
}
