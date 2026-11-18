<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FakeAppointmentsSeeder extends Seeder
{
    public function run(): void
    {
        $shopId = DB::table('shops')->first()->id;
        $professionals = DB::table('professionals')->where('shop_id', $shopId)->get();
        $services = DB::table('services')->where('shop_id', $shopId)->get();

        if ($professionals->isEmpty() || $services->isEmpty()) {
            return;
        }

        $clients = ['Carlos Silva', 'Fernando Souza', 'Roberto Almeida', 'Thiago Lima', 'Lucas Santos', 'Pedro Oliveira', 'Mateus Costa'];
        $phones = ['11999991111', '11999992222', '11999993333', '11999994444'];

        $appointments = [];

        // Gerar 30 agendamentos (alguns hoje, alguns passado, alguns futuro)
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::now();

            // Distribuir as datas: 30% hoje, 40% dias passados, 30% futuro
            $randDate = rand(1, 100);
            if ($randDate <= 40) {
                $date->subDays(rand(1, 15));
            } elseif ($randDate > 70) {
                $date->addDays(rand(1, 15));
            }

            // Hora aleatória entre 09:00 e 19:00
            $hour = rand(9, 18);
            $minute = rand(0, 1) == 0 ? '00' : '30';
            $date->setTime($hour, $minute);

            $service = $services->random();
            $professional = $professionals->random();

            // Status logic
            $status = 'confirmed';
            if ($date->isPast() && ! $date->isToday()) {
                $status = (rand(1, 10) > 8) ? 'no_show' : 'completed';
            } elseif ($date->isToday() && $date->hour < Carbon::now()->hour) {
                $status = 'completed';
            }

            $appointments[] = [
                'shop_id' => $shopId,
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'client_name' => $clients[array_rand($clients)],
                'client_phone' => $phones[array_rand($phones)],
                'scheduled_at' => $date->format('Y-m-d H:i:s'),
                'ends_at' => (clone $date)->addMinutes($service->duration_minutes)->format('Y-m-d H:i:s'),
                'status' => $status,
                'price_snapshot' => $service->price,
                'notes' => 'Gerado automaticamente pelo Seeder',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('appointments')->insertOrIgnore($appointments);
    }
}
