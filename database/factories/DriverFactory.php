<?php

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Liberty Market, Gulberg (Lahore).
     */
    public const float DEFAULT_LATITUDE = 31.5102;

    public const float DEFAULT_LONGITUDE = 74.3441;

    /**
     * Define the model's default state: an available Economy driver near Liberty Market.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'vehicle_category' => RideCategory::Economy,
            'vehicle_model' => fake()->randomElement(['Suzuki Alto', 'Suzuki Cultus', 'Toyota Corolla', 'Honda City', 'Kia Picanto']),
            'vehicle_plate' => fake()->unique()->bothify('LE?-####'),
            'status' => DriverStatus::Available,
            'latitude' => self::DEFAULT_LATITUDE,
            'longitude' => self::DEFAULT_LONGITUDE,
            'location_updated_at' => now(),
        ];
    }

    public function comfort(): static
    {
        return $this->state(fn (array $attributes) => [
            'vehicle_category' => RideCategory::Comfort,
            'vehicle_model' => fake()->randomElement(['Toyota Corolla Altis', 'Honda Civic', 'Toyota Yaris']),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => ['status' => DriverStatus::Offline]);
    }

    public function onTrip(): static
    {
        return $this->state(fn (array $attributes) => ['status' => DriverStatus::OnTrip]);
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_updated_at' => now(),
        ]);
    }
}
