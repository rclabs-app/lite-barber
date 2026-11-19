<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\ShopService;
use Illuminate\Http\Request;

class AdminShopController extends Controller
{
    public function __construct(
        private readonly ShopService $shopService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function show(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));

        return $this->shopService->findForTenant($shopId);
    }

    public function update(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));
        $shop = $this->shopService->findForTenant($shopId);

        $request->replace($this->shopService->normalizeTimeInputs($request->all()));

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'whatsapp_phone' => 'nullable|string|max:30',
            'instagram_url' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'maps_url' => 'nullable|string|max:500',
            'opening_time' => 'sometimes|required|date_format:H:i',
            'closing_time' => 'sometimes|required|date_format:H:i',
            'slot_duration' => 'sometimes|integer|min:10|max:180',
            'lunch_start' => 'nullable|date_format:H:i',
            'lunch_end' => 'nullable|date_format:H:i',
            'working_days' => 'sometimes|array',
            'working_days.*' => 'integer|between:0,6',
            'unavailable_dates' => 'nullable|array',
            'unavailable_dates.*' => 'date_format:Y-m-d',
            'is_active' => 'sometimes|boolean',
            'theme_color' => 'sometimes|string|max:20|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_mode' => 'sometimes|string|in:dark,light-nude,light-wine',
            'currency' => 'sometimes|string|in:BRL,USD,EUR',
            'locale' => 'sometimes|string|in:pt-BR,en-US,pt-PT',
            'timezone' => 'sometimes|string|max:64',
        ]);

        return response()->json($this->shopService->updateConfiguration($shop, $validated, $request->user()?->id));
    }

    public function updatePlan(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));
        $shop = $this->shopService->findForTenant($shopId);

        $validated = $request->validate([
            'plan' => 'required|string|in:free,starter,pro,premium',
        ]);

        $updated = $this->shopService->updatePlan($shop, $validated['plan'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'shop' => $updated,
        ]);
    }

    public function auditLogs(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));

        return response()->json([
            'success' => true,
            'logs' => $this->auditLogService->listForShop($shopId, (int) $request->query('limit', 30)),
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));
        $shop = $this->shopService->findForTenant($shopId);

        $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        return response()->json($this->shopService->uploadLogo($shop, $request->file('logo'), $request->user()?->id));
    }
}
