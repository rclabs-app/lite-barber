<?php

namespace Tests\Feature;

use App\Models\Professional;
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
}
