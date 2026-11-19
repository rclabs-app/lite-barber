<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Collection;

class AuditLogService
{
    public function record(
        int $shopId,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);
    }

    public function listForShop(int $shopId, int $limit = 30): Collection
    {
        return AuditLog::where('shop_id', $shopId)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
