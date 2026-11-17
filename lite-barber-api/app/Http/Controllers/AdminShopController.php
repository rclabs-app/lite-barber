<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class AdminShopController extends Controller
{
    public function show(Request $request)
    {
        return Shop::findOrFail($request->get('shop_id'));
    }

    public function update(Request $request)
    {
        $shop = Shop::findOrFail($request->get('shop_id'));

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'opening_time' => 'sometimes|string',
            'closing_time' => 'sometimes|string',
            'lunch_start' => 'nullable|string',
            'lunch_end' => 'nullable|string',
            'working_days' => 'sometimes|array',
            'unavailable_dates' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'theme_color' => 'sometimes|string',
            'theme_mode' => 'sometimes|string|in:dark,light-nude,light-wine'
        ]);

        $shop->update($request->only([
            'name', 'opening_time', 'closing_time', 'lunch_start', 'lunch_end', 'working_days', 'unavailable_dates', 'is_active', 'theme_color', 'theme_mode'
        ]));

        return response()->json($shop);
    }

    public function uploadLogo(Request $request)
    {
        $shop = Shop::findOrFail($request->get('shop_id'));

        $request->validate([
            'logo' => 'required|image|max:2048' // max 2MB
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            // Check if logo URL should include base API url or just relative.
            // A relative path is fine since Laravel serves it from /storage/logos/
            $shop->logo_url = '/storage/' . $path;
            $shop->save();
        }

        return response()->json($shop);
    }
}
