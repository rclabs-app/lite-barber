<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_routes_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'admin-rate-limit@teste.com',
                'password' => 'senha-incorreta',
            ]);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'admin-rate-limit@teste.com',
            'password' => 'senha-incorreta',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_public_write_routes_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/shops/barbearia-inexistente/waitlist', [
                'customer_name' => 'Cliente '.$attempt,
                'customer_phone' => '11999999999',
            ]);
        }

        $this->postJson('/api/shops/barbearia-inexistente/waitlist', [
            'customer_name' => 'Limite excedido',
            'customer_phone' => '11999999999',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_public_read_routes_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->getJson('/api/shops/barbearia-inexistente');
        }

        $this->getJson('/api/shops/barbearia-inexistente')
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }
}
