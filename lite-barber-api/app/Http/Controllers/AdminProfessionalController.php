<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Services\ShopService;
use Illuminate\Http\Request;

class AdminProfessionalController extends Controller
{
    public function __construct(
        private readonly ShopService $shopService
    ) {}

    public function index(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));

        return Professional::where('shop_id', $shopId)->get();
    }

    public function store(Request $request)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'specialties' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $this->shopService->assertCanCreateProfessional($shopId);

        $professional = Professional::create([
            'shop_id' => $shopId,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'specialties' => $validated['specialties'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'professional' => $professional,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));
        $professional = Professional::where('shop_id', $shopId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'specialties' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $professional->update($validated);

        return response()->json($professional);
    }

    public function destroy(Request $request, $id)
    {
        $shopId = (int) ($request->user()?->shop_id ?? $request->get('shop_id'));
        $professional = Professional::where('shop_id', $shopId)->findOrFail($id);
        $professional->delete();

        return response()->json(null, 204);
    }
}
