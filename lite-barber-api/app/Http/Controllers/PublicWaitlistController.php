<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Waitlist;
use Illuminate\Http\Request;

class PublicWaitlistController extends Controller
{
    // O cliente solicita entrada na fila ao vivo do balcão
    public function store(Request $request, $slug)
    {
        $shop = Shop::where('slug', $slug)->firstOrFail();
        
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $lastPosition = Waitlist::where('shop_id', $shop->id)
            ->whereDate('created_at', now()->today())
            ->max('position') ?? 0;

        $entry = Waitlist::create([
            'shop_id' => $shop->id,
            'client_name' => $request->customer_name,
            'client_phone' => $request->customer_phone,
            'position' => $lastPosition + 1,
            'status' => 'waiting',
        ]);

        return response()->json($entry, 201);
    }

    // Retorna a quantidade atual de pessoas na fila aguardando
    public function status($slug)
    {
        $shop = Shop::where('slug', $slug)->firstOrFail();
        
        $waiting = Waitlist::where('shop_id', $shop->id)
            ->where('status', 'waiting')
            ->orderBy('position')
            ->get(['client_name']);
            
        return response()->json([
            'waiting_count' => $waiting->count(),
            'names' => $waiting->pluck('client_name')
        ]);
    }
}
