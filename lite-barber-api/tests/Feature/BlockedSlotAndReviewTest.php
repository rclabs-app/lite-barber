<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BlockedSlotAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private Professional $professional;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create([
            'name' => 'Barbearia Vintage',
            'slug' => 'vintage-barber',
            'opening_time' => '08:00',
            'closing_time' => '19:00',
            'working_days' => [1, 2, 3, 4, 5, 6],
        ]);

        $this->owner = User::create([
            'name' => 'Carlos Dono',
            'email' => 'carlos@vintage.com',
            'password' => bcrypt('senha123'),
            'shop_id' => $this->shop->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->professional = Professional::create([
            'shop_id' => $this->shop->id,
            'name' => 'Barbeiro André',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'shop_id' => $this->shop->id,
            'name' => 'Corte Clássico',
            'price' => 50.00,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_manage_blocked_slots_and_conflict_is_prevented(): void
    {
        $targetDate = Carbon::now()->addDays(2)->format('Y-m-d');

        // 1. Admin cria bloqueio de horário (ex: almoço estendido das 11:00 às 13:00)
        $blockResponse = $this->actingAs($this->owner)->postJson('/api/admin/blocked-slots', [
            'professional_id' => $this->professional->id,
            'starts_at' => "{$targetDate} 11:00:00",
            'ends_at' => "{$targetDate} 13:00:00",
            'reason' => 'Consulta Médica',
        ]);

        $blockResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('blocked_slot.reason', 'Consulta Médica');

        $blockedSlotId = $blockResponse->json('blocked_slot.id');

        // 2. Endpoint público de horários ocupados deve listar o bloqueio
        $busyResponse = $this->getJson("/api/shops/{$this->shop->slug}/appointments/busy?date={$targetDate}&professional_id={$this->professional->id}");
        $busyResponse->assertOk();
        $this->assertTrue(collect($busyResponse->json())->contains(function ($slot) use ($targetDate) {
            return $slot['scheduled_at'] === "{$targetDate} 11:00:00" && $slot['ends_at'] === "{$targetDate} 13:00:00";
        }));

        // 3. Tentativa de agendamento público que conflita com o bloqueio deve ser rejeitada com 409
        $conflictResponse = $this->postJson("/api/shops/{$this->shop->slug}/appointments", [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => "{$targetDate} 11:30:00",
            'customer_name' => 'Cliente Bloqueado',
            'customer_phone' => '11988887777',
        ]);

        $conflictResponse->assertStatus(409)
            ->assertJsonFragment(['message' => 'O profissional possui um bloqueio de horário neste intervalo.']);

        // 4. Admin deleta o bloqueio
        $deleteResponse = $this->actingAs($this->owner)->deleteJson("/api/admin/blocked-slots/{$blockedSlotId}");
        $deleteResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('blocked_slots', ['id' => $blockedSlotId]);

        // 5. Agora o agendamento no mesmo horário passa normalmente
        $validResponse = $this->postJson("/api/shops/{$this->shop->slug}/appointments", [
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'scheduled_at' => "{$targetDate} 11:30:00",
            'customer_name' => 'Cliente Liberado',
            'customer_phone' => '11988887777',
        ]);

        $validResponse->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_customer_can_submit_review_after_appointment_completed(): void
    {
        $targetDate = Carbon::now()->addDays(1)->format('Y-m-d');

        $appointment = Appointment::create([
            'shop_id' => $this->shop->id,
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'client_name' => 'Renato Silva',
            'client_phone' => '11999998888',
            'scheduled_at' => "{$targetDate} 10:00:00",
            'ends_at' => "{$targetDate} 10:30:00",
            'price_snapshot' => 50.00,
            'status' => 'scheduled',
        ]);

        $this->assertNotNull($appointment->review_token);

        // Cliente tenta avaliar antes de concluir -> 422
        $earlyReview = $this->postJson("/api/reviews/{$appointment->review_token}", [
            'rating' => 5,
            'comment' => 'Ótimo corte!',
        ]);
        $earlyReview->assertStatus(422);

        // Admin conclui o atendimento -> status completed
        $completeResponse = $this->actingAs($this->owner)->patchJson("/api/admin/appointments/{$appointment->id}/status", [
            'status' => 'completed',
        ]);
        $completeResponse->assertOk()
            ->assertJsonPath('appointment.status', 'completed')
            ->assertJsonStructure(['review_whatsapp_url']);

        // Cliente consulta dados da avaliação pelo token
        $tokenQuery = $this->getJson("/api/reviews/{$appointment->review_token}");
        $tokenQuery->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.client_name', 'Renato Silva')
            ->assertJsonPath('already_reviewed', false);

        // Cliente envia avaliação
        $reviewResponse = $this->postJson("/api/reviews/{$appointment->review_token}", [
            'rating' => 5,
            'comment' => 'Atendimento impecável e corte perfeito!',
        ]);
        $reviewResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('review.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'shop_id' => $this->shop->id,
            'appointment_id' => $appointment->id,
            'rating' => 5,
        ]);

        // Tentativa de reavaliação duplicada -> 422
        $duplicateReview = $this->postJson("/api/reviews/{$appointment->review_token}", [
            'rating' => 4,
            'comment' => 'Outro comentário',
        ]);
        $duplicateReview->assertStatus(422);

        // Feed público de avaliações da barbearia
        $publicReviews = $this->getJson("/api/shops/{$this->shop->slug}/reviews");
        $publicReviews->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.total_reviews', 1)
            ->assertJsonPath('stats.average_rating', fn ($val) => (float) $val === 5.0)
            ->assertJsonPath('reviews.0.client_name', 'Renato Silva');

        // Admin modera e oculta avaliação
        $reviewId = $reviewResponse->json('review.id');
        $toggle = $this->actingAs($this->owner)->patchJson("/api/admin/reviews/{$reviewId}/toggle-approval");
        $toggle->assertOk()
            ->assertJsonPath('review.is_approved', false);

        // Agora não aparece mais no feed público
        $publicReviewsAfterHide = $this->getJson("/api/shops/{$this->shop->slug}/reviews");
        $publicReviewsAfterHide->assertJsonPath('stats.total_reviews', 0);
    }
}
