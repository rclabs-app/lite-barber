<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_validation_returns_standard_json_contract(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Os dados enviados são inválidos.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email', 'password'],
            ]);
    }

    public function test_missing_public_shop_returns_standard_not_found_contract(): void
    {
        $response = $this->getJson('/api/shops/barbearia-inexistente');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Recurso não encontrado.',
            ]);
    }

    public function test_protected_route_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/shop');

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Não autenticado.',
            ]);
    }
}
