<?php

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Shop;
use Carbon\Carbon;
use Faker\Factory;

$shop = Shop::first();
if (! $shop) {
    echo "No shop found.\n";

    return;
}

$professionals = Professional::where('shop_id', $shop->id)->get();
$services = Service::where('shop_id', $shop->id)->get();

if ($professionals->isEmpty() || $services->isEmpty()) {
    echo "Need professionals and services to seed.\n";

    return;
}

$startDate = Carbon::parse('2026-06-20');
$endDate = Carbon::parse('2026-06-21');

$totalDays = $startDate->diffInDays($endDate);
$faker = Factory::create('pt_BR');

$statuses = ['scheduled', 'scheduled', 'scheduled', 'confirmed', 'confirmed', 'cancelled'];
$createdCount = 0;

for ($i = 0; $i <= $totalDays; $i++) {
    $currentDate = $startDate->copy()->addDays($i);

    // if ($currentDate->isSunday()) {
    //     continue;
    // }

    $dailyAppointments = rand(5, 12);
    $bookedSlots = [];

    for ($j = 0; $j < $dailyAppointments; $j++) {
        $professional = $professionals->random();
        $service = $services->random();

        $maxRetries = 10;
        $slotFound = false;

        while ($maxRetries > 0) {
            $hour = rand(9, 17);
            $minute = rand(0, 1) === 0 ? '00' : '30';
            $timeString = sprintf('%02d:%s', $hour, $minute);

            $slotKey = $professional->id.'-'.$timeString;

            if (! isset($bookedSlots[$slotKey])) {
                $bookedSlots[$slotKey] = true;
                $slotFound = true;
                break;
            }
            $maxRetries--;
        }

        if (! $slotFound) {
            continue;
        }

        $scheduledAt = Carbon::parse($currentDate->format('Y-m-d').' '.$timeString);
        $endsAt = $scheduledAt->copy()->addMinutes($service->duration_minutes);

        try {
            Appointment::create([
                'shop_id' => $shop->id,
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'client_name' => $faker->firstName().' '.$faker->lastName(),
                'client_phone' => '119'.rand(10000000, 99999999),
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'status' => $statuses[array_rand($statuses)],
                'price_snapshot' => $service->price,
            ]);
            $createdCount++;
        } catch (Exception $e) {
            // Ignore duplicates
        }
    }
}

echo "Successfully created {$createdCount} appointments.\n";
