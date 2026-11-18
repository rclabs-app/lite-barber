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
            'opening_time' => 'sometimes|date_format:H:i',
            'closing_time' => 'sometimes|date_format:H:i',
            'lunch_start' => 'nullable|date_format:H:i',
            'lunch_end' => 'nullable|date_format:H:i',
            'working_days' => 'sometimes|array',
            'working_days.*' => 'integer|between:0,6',
            'unavailable_dates' => 'nullable|array',
            'unavailable_dates.*' => 'date_format:Y-m-d',
            'is_active' => 'sometimes|boolean',
            'theme_color' => 'sometimes|string|max:20|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_mode' => 'sometimes|string|in:dark,light-nude,light-wine'
        ]);

        $opening = $request->opening_time ?? $shop->opening_time;
        $closing = $request->closing_time ?? $shop->closing_time;

        if ($opening && $closing && $closing <= $opening) {
            return response()->json([
                'success' => false,
                'message' => 'O horário de fechamento deve ser posterior ao horário de abertura.',
            ], 422);
        }

        $lunchStart = $request->lunch_start ?? $shop->lunch_start;
        $lunchEnd = $request->lunch_end ?? $shop->lunch_end;

        if ($lunchStart && $lunchEnd && $lunchEnd <= $lunchStart) {
            return response()->json([
                'success' => false,
                'message' => 'O fim do intervalo deve ser posterior ao início.',
            ], 422);
        }

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
