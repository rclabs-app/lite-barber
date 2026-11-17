<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Criar a Barbearia Inicial (Sem dono ainda para evitar erro de Foreign Key circular)
        $shopId = DB::table('shops')->insertGetId([
            'name' => 'Elite Professional Collective',
            'slug' => 'elite-agenda',
            'phone' => '11999999999',
            'address' => 'Avenida Principal, 1000',
            'city' => 'São Paulo',
            'state' => 'SP',
            'opening_time' => '09:00:00',
            'closing_time' => '20:00:00',
            'slot_duration' => 30,
            'working_days' => json_encode([1, 2, 3, 4, 5, 6]),
            'plan' => 'premium',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Criar o Usuário Dono (Admin)
        $userId = DB::table('users')->insertGetId([
            'shop_id' => $shopId,
            'name' => 'Admin Owner',
            'email' => 'admin@eliteagenda.com',
            'password' => Hash::make('password'),
            'phone' => '11988888888',
            'role' => 'owner',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Atualizar a barbearia com seu verdadeiro dono
        DB::table('shops')->where('id', $shopId)->update(['owner_id' => $userId]);

        // 4. Cadastrar 2 Barbeiros
        $professionalId1 = DB::table('professionals')->insertGetId([
            'shop_id' => $shopId,
            'name' => 'João',
            'bio' => 'Barbeiro Clássico Mestre',
            'specialties' => json_encode(['corte', 'barboterapia']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $professionalId2 = DB::table('professionals')->insertGetId([
            'shop_id' => $shopId,
            'name' => 'Marcos',
            'bio' => 'Especialista em Degradê',
            'specialties' => json_encode(['corte', 'pigmentacao']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Cadastrar Serviços Iniciais
        DB::table('services')->insert([
            [
                'shop_id' => $shopId,
                'name' => 'Corte Premium',
                'description' => 'Corte com lavagem e toalha quente',
                'price' => 70.00,
                'duration_minutes' => 30,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shop_id' => $shopId,
                'name' => 'Barboterapia',
                'description' => 'Barba com hidratação e toalha quente',
                'price' => 50.00,
                'duration_minutes' => 30,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shop_id' => $shopId,
                'name' => 'Combo Elite (Corte+Barba)',
                'description' => 'A experiência completa de grooming',
                'price' => 110.00,
                'duration_minutes' => 60,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
