<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $result = $this->authService->loginAdmin($validated['email'], $validated['password']);

        return response()->json([
            'success' => true,
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }

    public function register(Request $request)
    {
        $request->merge([
            'slug' => Str::slug($request->input('slug', $request->input('shop_name'))),
        ]);

        $validated = $request->validate([
            'shop_name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:shops,slug',
            'admin_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $result = $this->authService->registerShopAndOwner($validated);

        return response()->json([
            'success' => true,
            'token' => $result['token'],
            'user' => $result['user'],
            'shop' => $result['shop'],
        ], 201);
    }

    public function me(Request $request)
    {
        $user = $request->user()->loadMissing('shop');

        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6',
        ]);

        $updatedUser = $this->authService->updateProfile($user, $validated);

        return response()->json([
            'message' => 'Perfil atualizado com sucesso',
            'user' => $updatedUser,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deslogado com sucesso',
        ]);
    }
}
