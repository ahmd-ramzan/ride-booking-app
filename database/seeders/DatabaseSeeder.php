<?php

namespace Database\Seeders;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development. Every account uses the password "password".
 * Never run against production.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command?->warn('Users already exist; skipping demo data.');

            return;
        }

        User::factory()->rider()->create(['name' => 'Ayesha Khan', 'email' => 'rider@example.com']);
        User::factory()->rider()->create(['name' => 'Bilal Ahmed', 'email' => 'rider2@example.com']);

        $drivers = [
            ['Usman Tariq', 'driver@example.com', RideCategory::Economy, 'Suzuki Cultus', 'LEA-1234', DriverStatus::Available, 31.5150, 74.3400],
            ['Hamza Ali', 'driver2@example.com', RideCategory::Economy, 'Suzuki Alto', 'LEB-2345', DriverStatus::Available, 31.5050, 74.3300],
            ['Sana Malik', 'driver3@example.com', RideCategory::Comfort, 'Toyota Corolla Altis', 'LEC-3456', DriverStatus::Available, 31.5200, 74.3550],
            ['Zainab Raza', 'driver4@example.com', RideCategory::Comfort, 'Honda Civic', 'LED-4567', DriverStatus::Available, 31.4750, 74.3600],
            ['Fahad Iqbal', 'driver5@example.com', RideCategory::Economy, 'Kia Picanto', 'LEE-5678', DriverStatus::Offline, 31.4700, 74.2700],
        ];

        foreach ($drivers as [$name, $email, $category, $model, $plate, $status, $latitude, $longitude]) {
            Driver::factory()
                ->for(User::factory()->driver()->state(['name' => $name, 'email' => $email]))
                ->at($latitude, $longitude)
                ->create([
                    'vehicle_category' => $category,
                    'vehicle_model' => $model,
                    'vehicle_plate' => $plate,
                    'status' => $status,
                ]);
        }
    }
}
