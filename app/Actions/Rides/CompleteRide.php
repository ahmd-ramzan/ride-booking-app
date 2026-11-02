<?php

namespace App\Actions\Rides;

use App\Enums\DriverStatus;
use App\Enums\RideStatus;
use App\Exceptions\InvalidRideTransitionException;
use App\Models\Driver;
use App\Models\Ride;
use App\Services\RideLifecycle;
use Illuminate\Support\Facades\DB;

class CompleteRide
{
    public function __construct(private RideLifecycle $lifecycle) {}

    /**
     * Completes the trip, settles the fare (upfront pricing: final = estimate)
     * and releases the driver at the drop-off location.
     *
     * @throws InvalidRideTransitionException
     */
    public function handle(Ride $ride, Driver $driver): Ride
    {
        return DB::transaction(function () use ($ride, $driver): Ride {
            $ride = Ride::query()->lockForUpdate()->findOrFail($ride->id);
            $this->lifecycle->ensureCanTransition($ride, RideStatus::Completed);

            $driver = Driver::query()->with('user')->lockForUpdate()->findOrFail($driver->id);

            $this->lifecycle->transition($ride, RideStatus::Completed, $driver->user, [
                'completed_at' => now(),
                'final_fare' => $ride->estimated_fare,
            ]);

            $driver->status = DriverStatus::Available;
            $driver->moveTo($ride->dropoff()->coordinates);
            $driver->save();

            return $ride;
        });
    }
}
