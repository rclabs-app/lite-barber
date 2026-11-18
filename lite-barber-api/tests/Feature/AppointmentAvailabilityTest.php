<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Professional $professional;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create([
            'name' => 'Barbearia Disponível',
            'slug' => 'barbearia-disponivel',
            'opening_time' => '09:00:00',
            'closing_time' => '18:00:00',
            'lunch_start' => '12:00:00',
            'lunch_end' => '13:00:00',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'unavailable_dates' => [],
            'is_active' => true,
        ]);

        $this->professional = Professional::create([
            'shop_id' => $this->shop->id,
            'name' => 'João',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'shop_id' => $this->shop->id,
            'name' => 'Corte',
            'price' => 50,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    public function test_public_customer_can_create_valid_appointment(): void
    {
        $scheduledAt = now()->copy()->addDays(7)->setTime(14, 0);

        $response = $this->postJson('/api/shops/barbearia-disponivel/appointments', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'customer_name' => 'Cliente Teste',
            'customer_phone' => '11999999999',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['appointment' => ['id', 'shop_id', 'professional_id', 'service_id']]);

        $this->assertDatabaseHas('appointments', [
            'shop_id' => $this->shop->id,
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_appointment_rejects_past_and_out_of_hours_slots(): void
    {
        $this->postJson('/api/shops/barbearia-disponivel/appointments', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'customer_name' => 'Cliente',
            'customer_phone' => '11999999999',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Não é possível agendar em um horário passado.']);

        $date = now()->copy()->addDays(7)->setTime(17, 30);

        $this->postJson('/api/shops/barbearia-disponivel/appointments', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $date->format('Y-m-d H:i:s'),
            'customer_name' => 'Cliente',
            'customer_phone' => '11999999999',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'O horário solicitado está fora do horário de funcionamento.']);
    }

    public function test_appointment_rejects_lunch_conflict(): void
    {
        $date = now()->copy()->addDays(7)->setTime(12, 30);

        $this->postJson('/api/shops/barbearia-disponivel/appointments', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $date->format('Y-m-d H:i:s'),
            'customer_name' => 'Cliente',
            'customer_phone' => '11999999999',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'O horário solicitado coincide com o intervalo de almoço.']);
    }

    public function test_appointment_rejects_overlapping_interval(): void
    {
        $scheduledAt = now()->copy()->addDays(7)->setTime(14, 0);

        $this->postJson('/api/shops/barbearia-disponivel/appointments', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'customer_name' => 'Primeiro',
            'customer_phone' => '11999999999',
        ])->assertCreated();

        $this->postJson('/api/shops/barbearia-disponivel/appointments', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $scheduledAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'customer_name' => 'Segundo',
            'customer_phone' => '11888888888',
        ])->assertConflict()
            ->assertJsonFragment(['message' => 'O horário solicitado já foi preenchido por outro cliente.']);
    }

    public function test_entities_from_another_tenant_are_rejected(): void
    {
        $otherShop = Shop::create([
            'name' => 'Outra Barbearia',
            'slug' => 'outra-barbearia',
            'is_active' => true,
        ]);

        $otherProfessional = Professional::create([
            'shop_id' => $otherShop->id,
            'name' => 'Maria',
            'is_active' => true,
        ]);

        $this->postJson('/api/shops/barbearia-disponivel/appointments', [
            'professional_id' => $otherProfessional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => now()->copy()->addDays(7)->setTime(14, 0)->format('Y-m-d H:i:s'),
            'customer_name' => 'Cliente',
            'customer_phone' => '11999999999',
        ])->assertNotFound();
    }
}
