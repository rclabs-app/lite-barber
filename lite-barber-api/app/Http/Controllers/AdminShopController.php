<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class AdminShopController extends Controller
{
    public function show(Request $request)
    {
        return Shop::findOrFail($request->user()?->shop_id ?? $request->get('shop_id'));
    }

    public function update(Request $request)
    {
        $shop = Shop::findOrFail($request->user()?->shop_id ?? $request->get('shop_id'));

        foreach (['opening_time', 'closing_time', 'lunch_start', 'lunch_end'] as $timeField) {
            if (! $request->exists($timeField)) {
                continue;
            }

            $value = $request->input($timeField);
            if ($value === '' || $value === null) {
                $request->merge([$timeField => null]);
            } elseif (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $request->merge([$timeField => substr($value, 0, 5)]);
            }
        }

        $request->validate([
            'name' => 'sometimes|string|max:100',
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
        ]);

        $openingRaw = $request->opening_time ?? $shop->opening_time;
        $closingRaw = $request->closing_time ?? $shop->closing_time;
        $opening = $openingRaw ? substr((string) $openingRaw, 0, 5) : null;
        $closing = $closingRaw ? substr((string) $closingRaw, 0, 5) : null;

        if ($opening && $closing && $closing <= $opening) {
            return response()->json([
                'success' => false,
                'message' => 'O horário de fechamento deve ser posterior ao horário de abertura.',
            ], 422);
        }

        $lunchStartRaw = $request->exists('lunch_start') ? $request->lunch_start : $shop->lunch_start;
        $lunchEndRaw = $request->exists('lunch_end') ? $request->lunch_end : $shop->lunch_end;
        $lunchStart = $lunchStartRaw ? substr((string) $lunchStartRaw, 0, 5) : null;
        $lunchEnd = $lunchEndRaw ? substr((string) $lunchEndRaw, 0, 5) : null;

        if (($lunchStart && ! $lunchEnd) || (! $lunchStart && $lunchEnd)) {
            return response()->json([
                'success' => false,
                'message' => 'Informe o início e o término do horário de almoço.',
            ], 422);
        }

        if ($lunchStart && $lunchEnd && $lunchEnd <= $lunchStart) {
            return response()->json([
                'success' => false,
                'message' => 'O fim do intervalo deve ser posterior ao início.',
            ], 422);
        }

        if ($lunchStart && $lunchEnd && $opening && $closing && ($lunchStart < $opening || $lunchEnd > $closing)) {
            return response()->json([
                'success' => false,
                'message' => 'O intervalo de almoço deve estar dentro do horário de funcionamento.',
            ], 422);
        }

        $shop->update($request->only([
            'name',
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
        ]));

        return response()->json($shop);
    }

    public function uploadLogo(Request $request)
    {
        $shop = Shop::findOrFail($request->user()?->shop_id ?? $request->get('shop_id'));

        $request->validate([
            'logo' => 'required|image|max:2048', // max 2MB
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            // Check if logo URL should include base API url or just relative.
            // A relative path is fine since Laravel serves it from /storage/logos/
            $shop->logo_url = '/storage/'.$path;
            $shop->save();
        }

        return response()->json($shop);
    }
}
