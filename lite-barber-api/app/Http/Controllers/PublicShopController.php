<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Shop;

class PublicShopController extends Controller
{
    public function show($slug)
    {
        $shop = Shop::where('slug', $slug)->where('is_active', true)->firstOrFail();
        return response()->json($shop);
    }

    public function services($slug)
    {
        $shop = Shop::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $services = $shop->services()->where('is_active', true)->orderBy('display_order')->get();
        return response()->json($services);
    }

    public function professionals($slug)
    {
        $shop = Shop::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $professionals = $shop->professionals()->where('is_active', true)->get();
        return response()->json($professionals);
    }
}
