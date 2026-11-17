<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function index(Request $request)
    {
        // shop_id injetado pelo middleware tenant
        return Service::where('shop_id', $request->get('shop_id'))->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'is_active' => 'boolean'
        ]);

        $service = Service::create([
            'shop_id' => $request->get('shop_id'),
            'name' => $request->name,
            'price' => $request->price,
            'duration_minutes' => $request->duration_minutes,
            'is_active' => $request->is_active ?? true
        ]);

        return response()->json($service, 201);
    }

    public function update(Request $request, $id)
    {
        $service = Service::where('shop_id', $request->get('shop_id'))->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'price' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean'
        ]);

        $service->update($request->all());

        return response()->json($service);
    }

    public function destroy(Request $request, $id)
    {
        $service = Service::where('shop_id', $request->get('shop_id'))->findOrFail($id);
        $service->delete();

        return response()->json(null, 204);
    }
}
