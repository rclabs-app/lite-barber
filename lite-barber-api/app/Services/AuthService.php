<?php

namespace App\Services;

use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    public function loginAdmin(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new HttpException(401, 'Credenciais inválidas');
        }

        if (! $user->is_active) {
            throw new HttpException(403, 'Usuário inativo.');
        }

        if (! in_array($user->role, ['owner', 'manager'], true) || ! $user->shop_id) {
            throw new HttpException(403, 'Usuário sem acesso administrativo.');
        }

        return [
            'token' => $user->createToken('admin_panel')->plainTextToken,
            'user' => $user,
        ];
    }

    public function registerShopAndOwner(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $shop = Shop::create([
                'name' => $data['shop_name'],
                'slug' => $data['slug'],
            ]);

            $user = User::create([
                'name' => $data['admin_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'shop_id' => $shop->id,
                'role' => 'owner',
                'is_active' => true,
            ]);

            $shop->update(['owner_id' => $user->id]);

            Professional::create([
                'shop_id' => $shop->id,
                'name' => $data['admin_name'],
                'is_active' => true,
            ]);

            Service::create([
                'shop_id' => $shop->id,
                'name' => 'Corte Tradicional',
                'description' => 'Corte masculino completo e acabamento.',
                'price' => 40.00,
                'duration_minutes' => 30,
                'display_order' => 1,
                'is_active' => true,
            ]);

            Service::create([
                'shop_id' => $shop->id,
                'name' => 'Barba Completa',
                'description' => 'Alinhamento e desenho de barba com toalha quente.',
                'price' => 30.00,
                'duration_minutes' => 20,
                'display_order' => 2,
                'is_active' => true,
            ]);

            return [
                'token' => $user->createToken('admin_panel')->plainTextToken,
                'user' => $user,
                'shop' => $shop,
            ];
        });
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->name = $data['name'];

        if (! empty($data['email'])) {
            $user->email = $data['email'];
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return $user;
    }
}
