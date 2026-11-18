<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use Illuminate\Http\Request;

class AdminProfessionalController extends Controller
{
    public function index(Request $request)
    {
        return Professional::where('shop_id', $request->get('shop_id'))->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'specialties' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $professional = Professional::create([
            'shop_id' => $request->get('shop_id'),
            'name' => $request->name,
            'phone' => $request->phone,
            'specialties' => $request->specialties,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'professional' => $professional,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $professional = Professional::where('shop_id', $request->get('shop_id'))->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'specialties' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $professional->update($request->all());

        return response()->json($professional);
    }

    public function destroy(Request $request, $id)
    {
        $professional = Professional::where('shop_id', $request->get('shop_id'))->findOrFail($id);
        $professional->delete();

        return response()->json(null, 204);
    }
}
