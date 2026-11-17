<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_only_sees_and_updates_services_from_their_tenant(): void
    {
        $shopA = Shop::create(['name' => 'Barbearia A', 'slug' => 'barbearia-a']);
        $shopB = Shop::create(['name' => 'Barbearia B', 'slug' => 'barbearia-b']);

        $userA = User::create([
            'shop_id' => $shopA->id,
            'name' => 'Admin A',
            'email' => 'admin-a@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $serviceA = Service::create([
            'shop_id' => $shopA->id,
            'name' => 'Corte A',
            'price' => 50,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        Service::create([
            'shop_id' => $shopB->id,
            'name' => 'Corte B',
            'price' => 60,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        Sanctum::actingAs($userA);

        $this->getJson('/api/admin/services')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Corte A'])
            ->assertJsonMissing(['name' => 'Corte B']);

        $this->putJson('/api/admin/services/'.$serviceA->id, [
            'name' => 'Corte Atualizado',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Corte Atualizado');

        $foreignService = Service::withoutGlobalScopes()->where('shop_id', $shopB->id)->firstOrFail();

        $this->putJson('/api/admin/services/'.$foreignService->id, [
            'name' => 'Não deveria alterar',
        ])
            ->assertNotFound();

        $this->assertDatabaseHas('services', [
            'id' => $foreignService->id,
            'name' => 'Corte B',
        ]);
    }

    public function test_inactive_or_professional_user_cannot_access_admin_routes(): void
    {
        $shop = Shop::create(['name' => 'Barbearia Teste', 'slug' => 'barbearia-teste']);

        $professional = User::create([
            'shop_id' => $shop->id,
            'name' => 'Profissional',
            'email' => 'profissional@teste.com',
            'password' => bcrypt('senha123'),
            'role' => 'professional',
            'is_active' => true,
        ]);

        Sanctum::actingAs($professional);

        $this->getJson('/api/admin/services')->assertForbidden();

        $professional->update(['role' => 'owner', 'is_active' => false]);

        Sanctum::actingAs($professional->fresh());

        $this->getJson('/api/admin/services')->assertForbidden();
    }
}
