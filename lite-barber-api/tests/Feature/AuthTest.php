<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_login_and_access_profile(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'shop_name' => 'Barbearia Teste',
            'slug' => 'barbearia-teste',
            'admin_name' => 'Administrador',
            'email' => 'admin@teste.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user', 'shop']);

        $shopId = $register->json('shop.id');
        $this->assertDatabaseHas('professionals', [
            'shop_id' => $shopId,
            'name' => 'Administrador',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('services', [
            'shop_id' => $shopId,
            'name' => 'Corte Tradicional',
        ]);
        $this->assertDatabaseHas('services', [
            'shop_id' => $shopId,
            'name' => 'Barba Completa',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'admin@teste.com',
            'password' => 'senha123',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user']);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'admin@teste.com')
            ->assertJsonStructure(['id', 'name', 'email', 'shop_id', 'shop']);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $login->json('user.id'),
        ]);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'shop_name' => 'Barbearia Confirmação',
            'slug' => 'barbearia-confirmacao',
            'admin_name' => 'Administrador',
            'email' => 'confirmacao@teste.com',
            'password' => 'senha123',
            'password_confirmation' => 'outra-senha',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'inexistente@teste.com',
            'password' => 'senha123',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }
}
