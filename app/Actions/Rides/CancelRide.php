<?php

namespace App\Actions\Rides;

use App\Enums\DriverStatus;
use App\Enums\RideStatus;
use App\Exceptions\InvalidRideTransitionException;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use App\Services\RideLifecycle;
use Illuminate\Support\Facades\DB;

class CancelRide
{
    public function __construct(private RideLifecycle $lifecycle) {}

    /**
     * Cancels a ride that has not started yet and frees the assigned driver.
     *
     * @throws InvalidRideTransitionException
     */
    public function handle(Ride $ride, User $actor, ?string $reason = null): Ride
    {
        return DB::transaction(function () use ($ride, $actor, $reason): Ride {
            $ride = Ride::query()->lockForUpdate()->findOrFail($ride->id);
            $this->lifecycle->ensureCanTransition($ride, RideStatus::Cancelled);

            if ($ride->driver_id !== null) {
                $driver = Driver::query()->lockForUpdate()->findOrFail($ride->driver_id);
                $driver->status = DriverStatus::Available;
                $driver->save();
            }

            return $this->lifecycle->transition($ride, RideStatus::Cancelled, $actor, [
                'cancelled_at' => now(),
                'cancelled_by' => $actor->role->value,
                'cancellation_reason' => $reason,
            ]);
        });
    }
}
