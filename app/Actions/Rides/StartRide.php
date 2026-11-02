<?php

namespace App\Actions\Rides;

use App\Enums\RideStatus;
use App\Exceptions\InvalidRideTransitionException;
use App\Models\Driver;
use App\Models\Ride;
use App\Services\RideLifecycle;
use Illuminate\Support\Facades\DB;

class StartRide
{
    public function __construct(private RideLifecycle $lifecycle) {}

    /**
     * Marks the rider as picked up. Only the assigned driver may start the ride
     * (enforced by RidePolicy::start).
     *
     * @throws InvalidRideTransitionException
     */
    public function handle(Ride $ride, Driver $driver): Ride
    {
        return DB::transaction(function () use ($ride, $driver): Ride {
            $ride = Ride::query()->lockForUpdate()->findOrFail($ride->id);

            return $this->lifecycle->transition($ride, RideStatus::InProgress, $driver->user, [
                'started_at' => now(),
            ]);
        });
    }
}
