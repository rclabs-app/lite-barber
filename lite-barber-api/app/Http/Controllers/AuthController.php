<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Shop;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        return response()->json([
            'token' => $user->createToken('admin_panel')->plainTextToken,
            'user' => $user
        ]);
    }

    public function register(Request $request)
    {
        $request->merge([
            'slug' => Str::slug($request->input('slug', $request->input('shop_name')))
        ]);

        $request->validate([
            'shop_name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:shops,slug',
            'admin_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6'
        ]);

        try {
            DB::beginTransaction();

            $shop = Shop::create([
                'name' => $request->shop_name,
                'slug' => $request->slug,
            ]);

            $user = User::create([
                'name' => $request->admin_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'shop_id' => $shop->id,
                'role' => 'owner',
                'is_active' => true
            ]);

            $shop->update(['owner_id' => $user->id]);

            DB::commit();

            return response()->json([
                'token' => $user->createToken('admin_panel')->plainTextToken,
                'user' => $user,
                'shop' => $shop
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao registrar', 'error' => $e->getMessage()], 500);
        }
    }

    public function me(Request $request) 
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6'
        ]);

        $user->name = $request->name;
        
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json(['message' => 'Perfil atualizado com sucesso', 'user' => $user]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Deslogado com sucesso']);
    }
}
