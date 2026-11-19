<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\ShopService;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function __construct(
        private readonly ShopService $shopService
    ) {}

    public function index(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));

        return Service::where('shop_id', $shopId)->get();
    }

    public function store(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'display_order' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $this->shopService->assertCanCreateService($shopId);

        $service = Service::create([
            'shop_id' => $shopId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'duration_minutes' => $validated['duration_minutes'],
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'service' => $service,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));
        $service = Service::where('shop_id', $shopId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:1',
            'display_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $service->update($validated);

        return response()->json($service);
    }

    public function destroy(Request $request, $id)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));
        $service = Service::where('shop_id', $shopId)->findOrFail($id);
        $service->delete();

        return response()->json(null, 204);
    }
}
