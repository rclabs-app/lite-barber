<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Models\Waitlist;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Professional $professional;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create([
            'name' => 'Barbearia Navalha de Ouro',
            'slug' => 'navalha-ouro',
            'opening_time' => '08:00',
            'closing_time' => '19:00',
            'working_days' => [1, 2, 3, 4, 5, 6],
        ]);

        $this->professional = Professional::create([
            'shop_id' => $this->shop->id,
            'name' => 'Mestre Barbeiro',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'shop_id' => $this->shop->id,
            'name' => 'Corte Degradê',
            'price' => 45.00,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
    }

    public function test_booking_creates_appointment_and_generates_whatsapp_url(): void
    {
        $targetDate = Carbon::now()->addDays(2)->format('Y-m-d');

        $response = $this->postJson("/api/shops/{$this->shop->slug}/appointments", [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => "{$targetDate} 10:00:00",
            'customer_name' => 'Carlos Cliente',
            'customer_phone' => '(11) 98765-4321',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['appointment', 'whatsapp_url']);

        $whatsappUrl = $response->json('whatsapp_url');
        $this->assertNotEmpty($whatsappUrl);
        $this->assertStringContainsString('https://wa.me/5511987654321', $whatsappUrl);
        $this->assertStringContainsString('Confirma%C3%A7%C3%A3o', $whatsappUrl);

        $appointment = Appointment::first();
        $this->assertNotNull($appointment->cancel_token);
        $this->assertStringContainsString($appointment->cancel_token, $whatsappUrl);
    }

    public function test_evolution_api_dispatch_when_configured(): void
    {
        config([
            'services.evolution.url' => 'https://evolution.example.com',
            'services.evolution.api_key' => 'secret-api-key',
            'services.evolution.instance' => 'litebarber',
        ]);

        Http::fake([
            'https://evolution.example.com/message/sendText/litebarber' => Http::response([
                'status' => 'SUCCESS',
                'id' => 'evo-msg-123',
            ], 200),
        ]);

        $service = app(WhatsAppNotificationService::class);
        $result = $service->dispatchViaEvolutionApi('11999998888', 'Mensagem de teste');

        $this->assertTrue($result['sent']);
        $this->assertSame('evolution', $result['provider']);
        $this->assertSame('SUCCESS', $result['response']['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://evolution.example.com/message/sendText/litebarber'
                && $request->header('apikey')[0] === 'secret-api-key'
                && $request['number'] === '5511999998888';
        });
    }

    public function test_cancellation_and_waitlist_notification_includes_whatsapp_url(): void
    {
        $owner = User::create([
            'name' => 'Dono',
            'email' => 'dono@teste.com',
            'password' => bcrypt('senha123'),
            'shop_id' => $this->shop->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $targetDate = Carbon::now()->addDays(3)->format('Y-m-d');

        // Cria entrada na lista de espera para o mesmo dia e horário
        $waitlistEntry = Waitlist::create([
            'shop_id' => $this->shop->id,
            'client_name' => 'Lucas Fila',
            'client_phone' => '11977776666',
            'desired_date' => $targetDate,
            'desired_time' => '14:00',
            'status' => 'waiting',
        ]);

        // Cria agendamento que será cancelado
        $appointment = Appointment::create([
            'shop_id' => $this->shop->id,
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'client_name' => 'Pedro Marcado',
            'client_phone' => '11988887777',
            'scheduled_at' => "{$targetDate} 14:00:00",
            'ends_at' => "{$targetDate} 14:30:00",
            'price_snapshot' => 45.00,
            'status' => 'scheduled',
        ]);

        // Admin cancela agendamento -> deve alertar Pedro e retornar Lucas com whatsapp_url
        $cancelResponse = $this->actingAs($owner)->patchJson("/api/admin/appointments/{$appointment->id}/status", [
            'status' => 'cancelled',
            'cancel_reason' => 'Imprevisto na barbearia',
        ]);

        $cancelResponse->assertOk()
            ->assertJsonPath('appointment.status', 'cancelled')
            ->assertJsonPath('waiting_clients.0.client_name', 'Lucas Fila');

        $waitingClients = $cancelResponse->json('waiting_clients');
        $this->assertNotEmpty($waitingClients[0]['whatsapp_url']);
        $this->assertStringContainsString('https://wa.me/5511977776666', $waitingClients[0]['whatsapp_url']);

        // Admin notifica Lucas na fila
        $notifyResponse = $this->actingAs($owner)->patchJson("/api/admin/waitlist/{$waitlistEntry->id}/notify");
        $notifyResponse->assertOk()
            ->assertJsonPath('entry.status', 'notified')
            ->assertJsonStructure(['entry', 'whatsapp_url']);

        $this->assertStringContainsString('https://wa.me/5511977776666', $notifyResponse->json('whatsapp_url'));
    }
}
