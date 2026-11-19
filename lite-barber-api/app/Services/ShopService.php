<?php

namespace App\Services;

use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShopService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function findActiveBySlug(string $slug): Shop
    {
        return Shop::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function findForTenant(int $shopId): Shop
    {
        return Shop::findOrFail($shopId);
    }

    public function getPlanLimits(Shop $shop): array
    {
        $isPro = in_array($shop->plan, ['pro', 'premium'], true);

        return [
            'max_professionals' => $isPro ? 8 : 2,
            'max_services' => $isPro ? 20 : 5,
        ];
    }

    public function assertCanCreateProfessional(int $shopId): void
    {
        $shop = $this->findForTenant($shopId);
        $limits = $this->getPlanLimits($shop);
        $currentCount = Professional::where('shop_id', $shopId)->count();

        if ($currentCount >= $limits['max_professionals']) {
            throw new HttpException(
                422,
                "Limite do plano atingido: seu plano permite até {$limits['max_professionals']} profissionais."
            );
        }
    }

    public function assertCanCreateService(int $shopId): void
    {
        $shop = $this->findForTenant($shopId);
        $limits = $this->getPlanLimits($shop);
        $currentCount = Service::where('shop_id', $shopId)->count();

        if ($currentCount >= $limits['max_services']) {
            throw new HttpException(
                422,
                "Limite do plano atingido: seu plano permite até {$limits['max_services']} serviços."
            );
        }
    }

    public function updatePlan(Shop $shop, string $nextPlan, ?int $userId = null): Shop
    {
        $targetIsPro = in_array($nextPlan, ['pro', 'premium'], true);

        if (! $targetIsPro) {
            $profCount = Professional::where('shop_id', $shop->id)->count();
            $servCount = Service::where('shop_id', $shop->id)->count();

            if ($profCount > 2 || $servCount > 5) {
                throw new HttpException(
                    422,
                    'Reduza a quantidade de profissionais (máx. 2) e serviços (máx. 5) antes de mudar para o plano Lite.'
                );
            }
        }

        $previousPlan = $shop->plan;
        $shop->plan = $nextPlan;
        $shop->save();

        $this->auditLogService->record(
            (int) $shop->id,
            $userId,
            'shop.plan_updated',
            'shop',
            (int) $shop->id,
            ['from' => $previousPlan, 'to' => $nextPlan]
        );

        return $shop;
    }

    public function normalizeTimeInputs(array $input): array
    {
        foreach (['opening_time', 'closing_time', 'lunch_start', 'lunch_end'] as $timeField) {
            if (! array_key_exists($timeField, $input)) {
                continue;
            }

            $value = $input[$timeField];
            if ($value === '' || $value === null) {
                $input[$timeField] = null;
            } elseif (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $input[$timeField] = substr($value, 0, 5);
            }
        }

        return $input;
    }

    public function updateConfiguration(Shop $shop, array $data, ?int $userId = null): Shop
    {
        $openingRaw = $data['opening_time'] ?? $shop->opening_time;
        $closingRaw = $data['closing_time'] ?? $shop->closing_time;
        $opening = $openingRaw ? substr((string) $openingRaw, 0, 5) : null;
        $closing = $closingRaw ? substr((string) $closingRaw, 0, 5) : null;

        if ($opening && $closing && $closing <= $opening) {
            throw new HttpException(422, 'O horário de fechamento deve ser posterior ao horário de abertura.');
        }

        $lunchStartRaw = array_key_exists('lunch_start', $data) ? $data['lunch_start'] : $shop->lunch_start;
        $lunchEndRaw = array_key_exists('lunch_end', $data) ? $data['lunch_end'] : $shop->lunch_end;
        $lunchStart = $lunchStartRaw ? substr((string) $lunchStartRaw, 0, 5) : null;
        $lunchEnd = $lunchEndRaw ? substr((string) $lunchEndRaw, 0, 5) : null;

        if (($lunchStart && ! $lunchEnd) || (! $lunchStart && $lunchEnd)) {
            throw new HttpException(422, 'Informe o início e o término do horário de almoço.');
        }

        if ($lunchStart && $lunchEnd && $lunchEnd <= $lunchStart) {
            throw new HttpException(422, 'O fim do intervalo deve ser posterior ao início.');
        }

        if ($lunchStart && $lunchEnd && $opening && $closing && ($lunchStart < $opening || $lunchEnd > $closing)) {
            throw new HttpException(422, 'O intervalo de almoço deve estar dentro do horário de funcionamento.');
        }

        $allowed = [
            'name',
            'description',
            'phone',
            'whatsapp_phone',
            'instagram_url',
            'address',
            'maps_url',
            'opening_time',
            'closing_time',
            'slot_duration',
            'lunch_start',
            'lunch_end',
            'working_days',
            'unavailable_dates',
            'is_active',
            'theme_color',
            'theme_mode',
            'currency',
            'locale',
            'timezone',
        ];

        $changes = array_intersect_key($data, array_flip($allowed));
        $shop->update($changes);

        $this->auditLogService->record(
            (int) $shop->id,
            $userId,
            'shop.config_updated',
            'shop',
            (int) $shop->id,
            ['fields' => array_keys($changes)]
        );

        return $shop;
    }

    public function uploadLogo(Shop $shop, UploadedFile $file, ?int $userId = null): Shop
    {
        $path = $file->store('logos', 'public');
        $shop->logo_url = '/storage/'.$path;
        $shop->save();

        $this->auditLogService->record(
            (int) $shop->id,
            $userId,
            'shop.logo_updated',
            'shop',
            (int) $shop->id,
            ['logo_url' => $shop->logo_url]
        );

        return $shop;
    }
}
