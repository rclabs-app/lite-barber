<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Waitlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicWaitlistController extends Controller
{
    // O cliente solicita entrada na fila ao vivo do balcão
    public function store(Request $request, $slug)
    {
        $shop = Shop::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $entry = DB::transaction(function () use ($shop, $request) {
            return Waitlist::create([
                'shop_id' => $shop->id,
                'client_name' => $request->customer_name,
                'client_phone' => $request->customer_phone,
                'status' => 'waiting',
            ]);
        });

        $position = Waitlist::where('shop_id', $shop->id)
            ->where('status', 'waiting')
            ->where('created_at', '<=', $entry->created_at)
            ->count();

        return response()->json([
            'success' => true,
            'entry' => $entry,
            'position' => $position,
        ], 201);
    }

    // Retorna a quantidade atual de pessoas na fila aguardando
    public function status($slug)
    {
        $shop = Shop::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $waiting = Waitlist::where('shop_id', $shop->id)
            ->where('status', 'waiting')
            ->orderBy('created_at')
            ->get(['id', 'client_name', 'created_at']);

        $entries = $waiting->values()->map(fn ($entry, $index) => [
            'position' => $index + 1,
            'name' => $entry->client_name,
        ]);

        return response()->json([
            'success' => true,
            'waiting_count' => $waiting->count(),
            'names' => $waiting->pluck('client_name'),
            'entries' => $entries,
        ]);
    }
}
