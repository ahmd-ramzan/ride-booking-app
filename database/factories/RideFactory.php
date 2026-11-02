<?php

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ride>
 */
class RideFactory extends Factory
{
    /**
     * Define the model's default state: a requested Economy ride from Liberty
     * Market to Packages Mall.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rider_id' => User::factory()->rider(),
            'driver_id' => null,
            'category' => RideCategory::Economy,
            'status' => RideStatus::Requested,
            'pickup_address' => 'Liberty Market, Gulberg III',
            'pickup_latitude' => 31.5102,
            'pickup_longitude' => 74.3441,
            'dropoff_address' => 'Packages Mall, Walton Road',
            'dropoff_latitude' => 31.4711,
            'dropoff_longitude' => 74.3555,
            'distance_meters' => 5_800,
            'duration_seconds' => 950,
            'currency' => 'PKR',
            'estimated_fare' => 420,
            'fare_breakdown' => ['base' => 120, 'distance' => 220, 'time' => 80, 'minimum_fare_applied' => false],
            'requested_at' => now(),
        ];
    }

    public function comfort(): static
    {
        return $this->state(fn (array $attributes) => ['category' => RideCategory::Comfort]);
    }

    /**
     * Accepted by the given driver (or a new one). The driver is put on trip
     * so that the data matches what AcceptRide would produce.
     */
    public function acceptedBy(?Driver $driver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RideStatus::Accepted,
            'driver_id' => $driver ?? Driver::factory()->onTrip(),
            'accepted_at' => now(),
        ])->afterCreating(function (Ride $ride): void {
            $ride->driver?->forceFill(['status' => DriverStatus::OnTrip])->save();
        });
    }

    public function inProgressWith(?Driver $driver = null): static
    {
        return $this->acceptedBy($driver)->state(fn (array $attributes) => [
            'status' => RideStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RideStatus::Completed,
            'driver_id' => Driver::factory(),
            'accepted_at' => now()->subMinutes(30),
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
            'final_fare' => $attributes['estimated_fare'],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RideStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => 'rider',
        ]);
    }
}
