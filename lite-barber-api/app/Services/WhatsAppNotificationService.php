<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Waitlist;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppNotificationService
{
    /**
     * Limpa o telefone mantendo apenas números e adiciona código do país (55 para Brasil) se ausente.
     */
    public function cleanPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) > 10) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        return $digits;
    }

    /**
     * Constrói link universal do WhatsApp com mensagem pré-formatada.
     */
    public function buildDirectWhatsAppUrl(string $phone, string $message): string
    {
        $digits = $this->cleanPhone($phone);

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    /**
     * Envia confirmação de agendamento ao cliente.
     */
    public function sendBookingConfirmation(Appointment $appointment): array
    {
        $appointment->loadMissing(['shop', 'professional', 'service']);

        $shopName = $appointment->shop?->name ?? 'Lite Barber';
        $clientName = $appointment->client_name;
        $professionalName = $appointment->professional?->name ?? 'Profissional';
        $serviceName = $appointment->service?->name ?? 'Serviço';
        $dateTime = $appointment->scheduled_at ? $appointment->scheduled_at->format('d/m/Y \à\s H:i') : '';
        $price = number_format((float) ($appointment->price_snapshot ?? $appointment->service?->price ?? 0), 2, ',', '.');

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $shopSlug = $appointment->shop?->slug ?? '';
        $cancelToken = $appointment->cancel_token;
        $manageUrl = $cancelToken ? "{$frontendUrl}/{$shopSlug}/appointment/{$cancelToken}" : "{$frontendUrl}/{$shopSlug}";

        $message = "✂️ *Confirmação de Agendamento*\n\n"
            ."Olá, *{$clientName}*! Seu horário na *{$shopName}* está confirmado com sucesso!\n\n"
            ."📅 *Data/Hora:* {$dateTime}\n"
            ."💈 *Profissional:* {$professionalName}\n"
            ."✂️ *Serviço:* {$serviceName}\n"
            ."💰 *Valor:* R$ {$price}\n\n"
            ."Caso precise cancelar ou consultar seu agendamento, acesse:\n"
            ."{$manageUrl}\n\n"
            .'Agradecemos a sua preferência!';

        $phone = (string) $appointment->client_phone;
        $whatsappUrl = $this->buildDirectWhatsAppUrl($phone, $message);
        $dispatchResult = $this->dispatchViaEvolutionApi($phone, $message);

        return array_merge($dispatchResult, [
            'message' => $message,
            'whatsapp_url' => $whatsappUrl,
        ]);
    }

    /**
     * Envia aviso de cancelamento de agendamento ao cliente.
     */
    public function sendCancellationNotice(Appointment $appointment, string $cancelledBy = 'admin', ?string $reason = null): array
    {
        $appointment->loadMissing(['shop', 'professional', 'service']);

        $shopName = $appointment->shop?->name ?? 'Lite Barber';
        $clientName = $appointment->client_name;
        $serviceName = $appointment->service?->name ?? 'Serviço';
        $dateTime = $appointment->scheduled_at ? $appointment->scheduled_at->format('d/m/Y \à\s H:i') : '';

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $shopSlug = $appointment->shop?->slug ?? '';
        $shopUrl = "{$frontendUrl}/{$shopSlug}";

        $reasonText = ! empty($reason) ? "\n*Motivo:* {$reason}" : '';

        $message = "⚠️ *Cancelamento de Agendamento*\n\n"
            ."Olá, *{$clientName}*! Informamos que seu agendamento na *{$shopName}* para *{$dateTime}* ({$serviceName}) foi cancelado.{$reasonText}\n\n"
            ."Se desejar escolher um novo horário, acesse:\n"
            ."{$shopUrl}\n\n"
            .'Pedimos desculpas por qualquer inconveniente.';

        $phone = (string) $appointment->client_phone;
        $whatsappUrl = $this->buildDirectWhatsAppUrl($phone, $message);
        $dispatchResult = $this->dispatchViaEvolutionApi($phone, $message);

        return array_merge($dispatchResult, [
            'message' => $message,
            'whatsapp_url' => $whatsappUrl,
        ]);
    }

    /**
     * Envia notificação para cliente na fila / lista de espera avisando que vaga surgiu.
     */
    public function sendWaitlistNotification(Waitlist $entry, ?string $slotDate = null, ?string $slotTime = null): array
    {
        $entry->loadMissing(['shop', 'professional', 'service']);

        $shopName = $entry->shop?->name ?? 'Lite Barber';
        $clientName = $entry->client_name;

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $shopSlug = $entry->shop?->slug ?? '';
        $bookingUrl = "{$frontendUrl}/{$shopSlug}/booking";

        $slotInfo = '';
        if ($slotDate && $slotTime) {
            $slotInfo = "\n📅 *Horário liberado:* {$slotDate} às {$slotTime}";
        }

        $message = "🔔 *Vaga Disponível na {$shopName}!*\n\n"
            ."Olá, *{$clientName}*! Uma vaga acabou de abrir na lista de espera!{$slotInfo}\n\n"
            ."Acesse o link abaixo para garantir seu atendimento antes que outro cliente preencha:\n"
            ."{$bookingUrl}\n\n"
            .'Esperamos você!';

        $phone = (string) $entry->client_phone;
        $whatsappUrl = $this->buildDirectWhatsAppUrl($phone, $message);
        $dispatchResult = $this->dispatchViaEvolutionApi($phone, $message);

        return array_merge($dispatchResult, [
            'message' => $message,
            'whatsapp_url' => $whatsappUrl,
        ]);
    }

    /**
     * Dispara mensagem pela Evolution API se configurada no .env.
     */
    public function dispatchViaEvolutionApi(string $phone, string $message): array
    {
        $url = config('services.evolution.url');
        $apiKey = config('services.evolution.api_key');
        $instance = config('services.evolution.instance', 'litebarber');

        $cleanPhone = $this->cleanPhone($phone);
        if (empty($cleanPhone)) {
            return [
                'sent' => false,
                'provider' => 'none',
                'reason' => 'Telefone inválido ou não informado',
            ];
        }

        if (empty($url) || empty($apiKey)) {
            return [
                'sent' => false,
                'provider' => 'direct_link',
                'reason' => 'Evolution API não configurada; gerado link wa.me para envio manual.',
            ];
        }

        try {
            $endpoint = rtrim((string) $url, '/')."/message/sendText/{$instance}";

            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(5)->post($endpoint, [
                'number' => $cleanPhone,
                'text' => $message,
            ]);

            if ($response->successful()) {
                return [
                    'sent' => true,
                    'provider' => 'evolution',
                    'response' => $response->json(),
                ];
            }

            Log::warning('Evolution API enviou resposta de erro', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'sent' => false,
                'provider' => 'evolution',
                'error' => $response->body(),
            ];
        } catch (Throwable $e) {
            Log::warning('Falha ao comunicar com Evolution API: '.$e->getMessage());

            return [
                'sent' => false,
                'provider' => 'evolution',
                'error' => $e->getMessage(),
            ];
        }
    }
}
