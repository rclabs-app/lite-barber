<?php

namespace App\Services;

use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Waitlist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WaitlistService
{
    public function listForShop(int $shopId, int $perPage = 20): LengthAwarePaginator
    {
        $waitingIds = Waitlist::where('shop_id', $shopId)
            ->where('status', 'waiting')
            ->orderBy('desired_date', 'asc')
            ->orderBy('desired_time', 'asc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->values();

        $positionMap = $waitingIds->flip()->map(fn ($index) => $index + 1);

        $list = Waitlist::where('shop_id', $shopId)
            ->with(['professional', 'service'])
            ->whereIn('status', ['waiting', 'notified'])
            ->orderBy('desired_date', 'asc')
            ->orderBy('desired_time', 'asc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        $list->getCollection()->transform(function (Waitlist $entry) use ($positionMap) {
            $entry->setAttribute('position', $positionMap->get($entry->id));

            return $entry;
        });

        return $list;
    }

    public function createAdminEntry(int $shopId, array $data): Waitlist
    {
        if (! empty($data['professional_id']) && ! Professional::where('shop_id', $shopId)->whereKey($data['professional_id'])->exists()) {
            throw new HttpException(422, 'Profissional não pertence a esta barbearia.');
        }

        if (! empty($data['service_id']) && ! Service::where('shop_id', $shopId)->whereKey($data['service_id'])->exists()) {
            throw new HttpException(422, 'Serviço não pertence a esta barbearia.');
        }

        $entry = Waitlist::create([
            'shop_id' => $shopId,
            'client_name' => $data['client_name'],
            'client_phone' => $data['client_phone'],
            'desired_date' => $data['desired_date'],
            'desired_time' => $data['desired_time'],
            'professional_id' => $data['professional_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'waiting',
        ]);

        return $entry->load(['professional', 'service']);
    }

    public function createPublicEntry(Shop $shop, array $data): array
    {
        $entry = DB::transaction(function () use ($shop, $data) {
            return Waitlist::create([
                'shop_id' => $shop->id,
                'client_name' => $data['customer_name'],
                'client_phone' => $data['customer_phone'] ?? null,
                'status' => 'waiting',
            ]);
        });

        $position = Waitlist::where('shop_id', $shop->id)
            ->where('status', 'waiting')
            ->where('id', '<=', $entry->id)
            ->count();

        return [
            'entry' => $entry,
            'position' => $position,
        ];
    }

    public function publicStatus(Shop $shop): array
    {
        $waiting = Waitlist::where('shop_id', $shop->id)
            ->where('status', 'waiting')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'client_name', 'created_at']);

        $entries = $waiting->values()->map(fn ($entry, $index) => [
            'position' => $index + 1,
            'name' => $entry->client_name,
        ]);

        return [
            'waiting_count' => $waiting->count(),
            'names' => $waiting->pluck('client_name'),
            'entries' => $entries,
        ];
    }

    public function markNotified(int $shopId, int $id): Waitlist
    {
        $entry = Waitlist::where('shop_id', $shopId)->findOrFail($id);

        if ($entry->status !== 'waiting') {
            throw new HttpException(422, 'Somente entradas aguardando podem ser notificadas.');
        }

        $entry->status = 'notified';
        $entry->notified_at = now();
        $entry->save();

        return $entry;
    }

    public function cancelEntry(int $shopId, int $id): Waitlist
    {
        $entry = Waitlist::where('shop_id', $shopId)->findOrFail($id);

        if ($entry->status === 'cancelled') {
            throw new HttpException(422, 'Esta entrada já foi removida.');
        }

        $entry->status = 'cancelled';
        $entry->save();

        return $entry;
    }

    public function findMatchingWaitingClients(int $shopId, string $date, string $time, ?int $professionalId = null): array
    {
        $shortTime = substr($time, 0, 5);

        $query = Waitlist::where('shop_id', $shopId)
            ->where('status', 'waiting')
            ->where(function ($q) use ($date, $shortTime) {
                $q->where(function ($slotQuery) use ($date, $shortTime) {
                    $slotQuery->whereDate('desired_date', $date)
                        ->where(function ($timeQuery) use ($shortTime) {
                            $timeQuery->whereIn('desired_time', [$shortTime, $shortTime.':00'])
                                ->orWhereNull('desired_time');
                        });
                })->orWhere(function ($walkInQuery) {
                    $walkInQuery->whereNull('desired_date')
                        ->whereNull('desired_time');
                });
            });

        if ($professionalId) {
            $query->where(function ($q) use ($professionalId) {
                $q->where('professional_id', $professionalId)->orWhereNull('professional_id');
            });
        }

        return $query->with(['professional', 'service'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->values()
            ->map(function (Waitlist $entry, int $index) {
                $entry->setAttribute('position', $index + 1);

                return $entry;
            })
            ->toArray();
    }
}
